<?php
/**
 * Intégration CRM — architecture modulaire.
 * HubSpot est la première implémentation ; d'autres fournisseurs
 * s'ajoutent en étendant IAM_Leads_CRM_Fournisseur et en s'enregistrant
 * via le filtre `iam_leads_crm_fournisseurs`.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IAM_Leads_CRM_Fournisseur {

	/** Identifiant technique du fournisseur. */
	abstract public function id();

	/** Nom affiché dans les réglages. */
	abstract public function nom();

	/**
	 * Envoie un lead.
	 *
	 * @param object $lead Lead.
	 * @return true|WP_Error
	 */
	abstract public function envoyer( $lead );

	/**
	 * Champs mappés communs à tous les fournisseurs.
	 */
	protected function payload_commun( $lead ) {
		$prenom = '';
		$nom    = '';

		if ( $lead->nom_complet ) {
			$morceaux = explode( ' ', $lead->nom_complet, 2 );
			$prenom   = $morceaux[0];
			$nom      = $morceaux[1] ?? '';
		}

		return array(
			'firstname'          => $prenom,
			'lastname'           => $nom,
			'email'              => $lead->email,
			'phone'              => $lead->telephone,
			'country'            => $lead->pays,
			'iam_programme'      => $lead->programme,
			'iam_niveau_etudes'  => $lead->niveau,
			'iam_contact_prefere'=> $lead->contact_prefere,
			'iam_source'         => $lead->source,
			'iam_reseau_social'  => $lead->reseau_social,
			'iam_utm_source'     => $lead->utm_source,
			'iam_utm_medium'     => $lead->utm_medium,
			'iam_utm_campaign'   => $lead->utm_campaign,
			'iam_utm_content'    => $lead->utm_content,
			'iam_utm_term'       => $lead->utm_term,
			'iam_page_origine'   => $lead->page_origine,
			'iam_page_formulaire'=> $lead->page_soumission,
		);
	}
}

/**
 * Implémentation HubSpot (API Contacts v3, Private App Token).
 */
class IAM_Leads_CRM_HubSpot extends IAM_Leads_CRM_Fournisseur {

	const ENDPOINT = 'https://api.hubapi.com/crm/v3/objects/contacts';

	public function id() {
		return 'hubspot';
	}

	public function nom() {
		return 'HubSpot';
	}

	public function envoyer( $lead ) {
		$token = trim( (string) IAM_Leads::get_setting( 'hubspot_token' ) );

		if ( '' === $token ) {
			return new WP_Error( 'iam_crm_token', __( 'Jeton HubSpot absent : renseignez-le dans les réglages.', 'iam-leads' ) );
		}

		$proprietes = array_filter(
			$this->payload_commun( $lead ),
			static function ( $valeur ) {
				return '' !== $valeur && null !== $valeur;
			}
		);

		$reponse = wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'properties' => $proprietes ) ),
			)
		);

		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		$code = wp_remote_retrieve_response_code( $reponse );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		// 409 : le contact existe déjà, on considère la synchronisation comme réussie.
		if ( 409 === $code ) {
			return true;
		}

		return new WP_Error(
			'iam_crm_http',
			sprintf(
				/* translators: 1 : code HTTP, 2 : réponse de l'API */
				__( 'HubSpot a répondu %1$d : %2$s', 'iam-leads' ),
				$code,
				wp_remote_retrieve_body( $reponse )
			)
		);
	}
}

/**
 * Point d'entrée de l'envoi CRM.
 */
class IAM_Leads_CRM_Integration {

	/**
	 * Liste des fournisseurs disponibles.
	 *
	 * @return IAM_Leads_CRM_Fournisseur[]
	 */
	public static function fournisseurs() {
		$liste = array( new IAM_Leads_CRM_HubSpot() );

		/**
		 * Permet d'ajouter un fournisseur CRM (Salesforce, Airtable…).
		 *
		 * @param IAM_Leads_CRM_Fournisseur[] $liste Fournisseurs.
		 */
		return apply_filters( 'iam_leads_crm_fournisseurs', $liste );
	}

	/**
	 * Retourne le fournisseur actif.
	 */
	public static function fournisseur_actif() {
		$id = IAM_Leads::get_setting( 'crm_fournisseur', 'hubspot' );

		foreach ( self::fournisseurs() as $fournisseur ) {
			if ( $fournisseur->id() === $id ) {
				return $fournisseur;
			}
		}

		return null;
	}

	/**
	 * Envoie un lead vers le CRM et enregistre le résultat.
	 *
	 * @param object $lead Lead.
	 * @return bool
	 */
	public static function envoyer( $lead ) {
		if ( ! IAM_Leads::get_setting( 'crm_active' ) ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'crm_status', 'desactive' );
			return false;
		}

		$fournisseur = self::fournisseur_actif();

		if ( ! $fournisseur ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'crm_status', 'echec' );
			IAM_Leads_Logger::ajouter( 'crm', 'echec', __( 'Aucun fournisseur CRM configuré.', 'iam-leads' ), $lead->id );
			return false;
		}

		$resultat = $fournisseur->envoyer( $lead );

		if ( is_wp_error( $resultat ) ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'crm_status', 'echec' );
			IAM_Leads_Logger::ajouter( 'crm', 'echec', $resultat->get_error_message(), $lead->id );

			// Nouvelle tentative dans 5 minutes.
			wp_schedule_single_event( time() + 300, 'iam_leads_retry_crm', array( (int) $lead->id ) );

			return false;
		}

		IAM_Leads::instance()->leads->update_envoi( $lead->id, 'crm_status', 'envoye' );
		IAM_Leads_Logger::ajouter( 'crm', 'succes', $fournisseur->nom() . ' : lead synchronisé.', $lead->id );

		return true;
	}

	/**
	 * Nouvelle tentative planifiée.
	 */
	public static function retry( $lead_id ) {
		$lead = IAM_Leads::instance()->leads->get( $lead_id );

		if ( ! $lead || 'envoye' === $lead->crm_status ) {
			return;
		}

		$fournisseur = self::fournisseur_actif();

		if ( ! $fournisseur ) {
			return;
		}

		$resultat = $fournisseur->envoyer( $lead );

		if ( is_wp_error( $resultat ) ) {
			IAM_Leads_Logger::ajouter( 'crm', 'echec', __( 'Nouvelle tentative échouée : ', 'iam-leads' ) . $resultat->get_error_message(), $lead_id );
			return;
		}

		IAM_Leads::instance()->leads->update_envoi( $lead_id, 'crm_status', 'envoye' );
		IAM_Leads_Logger::ajouter( 'crm', 'succes', __( 'Lead synchronisé après nouvelle tentative.', 'iam-leads' ), $lead_id );
	}
}

add_action( 'iam_leads_retry_crm', array( 'IAM_Leads_CRM_Integration', 'retry' ) );
