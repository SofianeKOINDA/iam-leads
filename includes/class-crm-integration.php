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
 * Implémentation IAM CRM (CRM interne du Groupe IAM, API Laravel).
 *
 * Le lead est envoyé tel que saisi sur le site : c'est le CRM qui traduit les
 * libellés (programme, source, niveau, pays). L'identifiant du lead rend
 * l'envoi idempotent : une nouvelle tentative ne crée jamais de doublon.
 */
class IAM_Leads_CRM_IamCrm extends IAM_Leads_CRM_Fournisseur {

	public function id() {
		return 'iam_crm';
	}

	public function nom() {
		return 'IAM CRM';
	}

	/** Adresse complète de l'endpoint, construite depuis l'URL saisie dans les réglages. */
	public static function endpoint() {
		$base = untrailingslashit( trim( (string) IAM_Leads::get_setting( 'iam_crm_url' ) ) );

		if ( '' === $base ) {
			return '';
		}

		// On accepte « https://crm.groupeiam.com » comme « https://crm.groupeiam.com/api ».
		if ( ! preg_match( '#/api$#', $base ) ) {
			$base .= '/api';
		}

		return $base . '/integrations/iam-leads';
	}

	public function envoyer( $lead ) {
		$url   = self::endpoint();
		$token = trim( (string) IAM_Leads::get_setting( 'iam_crm_token' ) );

		if ( '' === $url || '' === $token ) {
			return new WP_Error( 'iam_crm_config', __( 'IAM CRM : URL ou clé API absente dans les réglages.', 'iam-leads' ) );
		}

		$form = $lead->form_id ? IAM_Leads_Form_Builder::get( $lead->form_id ) : null;

		$payload = array(
			'lead_id'         => (int) $lead->id,
			'site'            => home_url(),
			'form_nom'        => $form ? $form->nom : '',
			'nom_complet'     => $lead->nom_complet,
			'email'           => $lead->email,
			'telephone'       => $lead->telephone,
			'pays'            => $lead->pays,
			'programme'       => $lead->programme,
			'niveau'          => $lead->niveau,
			'contact_prefere' => $lead->contact_prefere,
			'source'          => $lead->source,
			'reseau_social'   => $lead->reseau_social,
			'utm_source'      => $lead->utm_source,
			'utm_medium'      => $lead->utm_medium,
			'utm_campaign'    => $lead->utm_campaign,
			'utm_content'     => $lead->utm_content,
			'utm_term'        => $lead->utm_term,
			'page_origine'    => $lead->page_origine,
			'page_soumission' => $lead->page_soumission,
			'date_creation'   => $lead->date_creation,
		);

		$reponse = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode(
					array_filter(
						$payload,
						static function ( $valeur ) {
							return '' !== $valeur && null !== $valeur;
						}
					)
				),
			)
		);

		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		$code = wp_remote_retrieve_response_code( $reponse );

		// 200 : déjà connu (fusionné ou déjà synchronisé) ; 201 : prospect créé.
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		$corps   = json_decode( wp_remote_retrieve_body( $reponse ), true );
		$details = is_array( $corps ) && ! empty( $corps['message'] ) ? $corps['message'] : wp_remote_retrieve_body( $reponse );

		return new WP_Error(
			'iam_crm_http',
			sprintf(
				/* translators: 1 : code HTTP, 2 : message renvoyé par le CRM */
				__( 'IAM CRM a répondu %1$d : %2$s', 'iam-leads' ),
				$code,
				wp_html_excerpt( (string) $details, 300 )
			)
		);
	}
}

/**
 * Point d'entrée de l'envoi CRM.
 */
class IAM_Leads_CRM_Integration {

	/**
	 * Répartition des leads selon leur envoi au CRM.
	 *
	 * @return array{envoye:int,echec:int,en_attente:int}
	 */
	public static function compteurs() {
		global $wpdb;

		$lignes = (array) $wpdb->get_results( 'SELECT crm_status, COUNT(*) AS n FROM ' . IAM_Leads_Lead_Manager::table() . ' GROUP BY crm_status' ); // phpcs:ignore
		$etats  = array(
			'envoye'     => 0,
			'echec'      => 0,
			'en_attente' => 0,
		);

		foreach ( $lignes as $ligne ) {
			$cle            = isset( $etats[ $ligne->crm_status ] ) && 'en_attente' !== $ligne->crm_status ? $ligne->crm_status : 'en_attente';
			$etats[ $cle ] += (int) $ligne->n;
		}

		return $etats;
	}

	/**
	 * Teste la connexion au CRM choisi, sans créer de prospect.
	 *
	 * IAM CRM : une requête vide sur l'endpoint passe l'authentification
	 * puis la validation la refuse (422) : clé et adresse sont donc bonnes.
	 * HubSpot : lecture d'un contact avec le jeton.
	 *
	 * @return array{ok:bool,message:string,fournisseur:string,date:string}
	 */
	public static function tester() {
		$fournisseur = (string) IAM_Leads::get_setting( 'crm_fournisseur', 'iam_crm' );
		$resultat    = array(
			'ok'          => false,
			'message'     => '',
			'fournisseur' => 'hubspot' === $fournisseur ? 'HubSpot' : 'IAM CRM',
			'date'        => current_time( 'mysql' ),
		);

		if ( 'hubspot' === $fournisseur ) {
			$jeton = trim( (string) IAM_Leads::get_setting( 'hubspot_token' ) );

			if ( '' === $jeton ) {
				$resultat['message'] = __( 'Aucun jeton HubSpot enregistré.', 'iam-leads' );
				return self::memoriser( $resultat );
			}

			$reponse = wp_remote_get(
				'https://api.hubapi.com/crm/v3/objects/contacts?limit=1',
				array(
					'timeout' => 8,
					'headers' => array( 'Authorization' => 'Bearer ' . $jeton ),
				)
			);
			$attendu = 200;
		} else {
			$url   = IAM_Leads_CRM_IamCrm::endpoint();
			$token = trim( (string) IAM_Leads::get_setting( 'iam_crm_token' ) );

			if ( '' === $url || '' === $token ) {
				$resultat['message'] = __( 'Renseignez l\'adresse du CRM et la clé API, puis enregistrez.', 'iam-leads' );
				return self::memoriser( $resultat );
			}

			$reponse = wp_remote_post(
				$url,
				array(
					'timeout' => 8,
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
						'Content-Type'  => 'application/json',
						'Accept'        => 'application/json',
					),
					'body'    => '{}',
				)
			);
			$attendu = 422;
		}

		if ( is_wp_error( $reponse ) ) {
			$resultat['message'] = sprintf(
				/* translators: %s : erreur réseau */
				__( 'CRM injoignable : %s', 'iam-leads' ),
				$reponse->get_error_message()
			);
			return self::memoriser( $resultat );
		}

		$code = (int) wp_remote_retrieve_response_code( $reponse );

		if ( $attendu === $code ) {
			$resultat['ok']      = true;
			$resultat['message'] = __( 'Connexion réussie : le CRM a accepté la clé.', 'iam-leads' );
		} elseif ( 401 === $code || 403 === $code ) {
			$resultat['message'] = __( 'Le CRM répond, mais refuse la clé API (401).', 'iam-leads' );
		} elseif ( 404 === $code ) {
			$resultat['message'] = __( 'Adresse introuvable (404) : vérifiez l\'adresse du CRM.', 'iam-leads' );
		} else {
			$resultat['message'] = sprintf(
				/* translators: %d : code HTTP */
				__( 'Réponse inattendue du CRM (code %d).', 'iam-leads' ),
				$code
			);
		}

		return self::memoriser( $resultat );
	}

	/**
	 * Garde le dernier résultat de test (affiché dans les réglages) et le journalise.
	 *
	 * @param array $resultat Résultat.
	 * @return array
	 */
	private static function memoriser( $resultat ) {
		update_option( 'iam_leads_crm_test', $resultat, false );
		IAM_Leads_Logger::ajouter( 'crm', $resultat['ok'] ? 'succes' : 'echec', 'Test de connexion ' . $resultat['fournisseur'] . ' : ' . $resultat['message'] );

		return $resultat;
	}

	/**
	 * Liste des fournisseurs disponibles.
	 *
	 * @return IAM_Leads_CRM_Fournisseur[]
	 */
	public static function fournisseurs() {
		$liste = array( new IAM_Leads_CRM_IamCrm(), new IAM_Leads_CRM_HubSpot() );

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

	/**
	 * Rattrapage : envoie au CRM les leads jamais synchronisés ou en échec,
	 * par lots de 25 pour ne pas dépasser le temps d'exécution PHP. Chaque lot
	 * planifie le suivant tant qu'il en reste.
	 *
	 * @return int Nombre de leads traités dans ce lot.
	 */
	public static function synchroniser_lot() {
		global $wpdb;

		$fournisseur = self::fournisseur_actif();

		if ( ! $fournisseur || ! IAM_Leads::get_setting( 'crm_active' ) ) {
			delete_option( 'iam_leads_crm_sync_curseur' );
			return 0;
		}

		$table   = $wpdb->prefix . IAM_LEADS_TABLE_LEADS;
		$curseur = (int) get_option( 'iam_leads_crm_sync_curseur', 0 );
		$ids     = $wpdb->get_col( // phpcs:ignore
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE crm_status <> 'envoye' AND id > %d ORDER BY id ASC LIMIT 25", // phpcs:ignore
				$curseur
			)
		);

		$reussis = 0;
		foreach ( $ids as $id ) {
			$lead     = IAM_Leads::instance()->leads->get( $id );
			$resultat = $lead ? $fournisseur->envoyer( $lead ) : null;

			if ( true === $resultat ) {
				IAM_Leads::instance()->leads->update_envoi( $id, 'crm_status', 'envoye' );
				++$reussis;
			} elseif ( is_wp_error( $resultat ) ) {
				IAM_Leads::instance()->leads->update_envoi( $id, 'crm_status', 'echec' );
				IAM_Leads_Logger::ajouter( 'crm', 'echec', __( 'Synchronisation : ', 'iam-leads' ) . $resultat->get_error_message(), (int) $id );
			}
			$curseur = (int) $id;
		}

		if ( count( $ids ) === 25 ) {
			update_option( 'iam_leads_crm_sync_curseur', $curseur, false );
			wp_schedule_single_event( time() + 10, 'iam_leads_sync_crm' );
		} else {
			delete_option( 'iam_leads_crm_sync_curseur' );
			IAM_Leads_Logger::ajouter( 'crm', 'succes', __( 'Synchronisation des leads existants terminée.', 'iam-leads' ) );
		}

		if ( $ids ) {
			IAM_Leads_Logger::ajouter(
				'crm',
				'succes',
				sprintf(
					/* translators: 1 : leads envoyés, 2 : leads traités */
					__( 'Synchronisation : %1$d lead(s) envoyé(s) sur %2$d.', 'iam-leads' ),
					$reussis,
					count( $ids )
				)
			);
		}

		return count( $ids );
	}

	/** Lance (ou relance depuis le début) la synchronisation des leads existants. */
	public static function demarrer_synchronisation() {
		delete_option( 'iam_leads_crm_sync_curseur' );
		wp_clear_scheduled_hook( 'iam_leads_sync_crm' );

		// Premier lot tout de suite : l'administrateur voit le résultat dans le journal.
		self::synchroniser_lot();
	}
}

add_action( 'iam_leads_retry_crm', array( 'IAM_Leads_CRM_Integration', 'retry' ) );
add_action( 'iam_leads_sync_crm', array( 'IAM_Leads_CRM_Integration', 'synchroniser_lot' ) );
