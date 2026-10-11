<?php
/**
 * Notifications WhatsApp — architecture modulaire.
 * Meta Cloud API et Twilio sont fournis ; un autre fournisseur s'ajoute
 * via le filtre `iam_leads_whatsapp_fournisseurs`.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class IAM_Leads_WhatsApp_Fournisseur {

	abstract public function id();

	abstract public function nom();

	/**
	 * Envoie un message à un destinataire.
	 *
	 * @param string $destinataire Numéro au format international.
	 * @param string $message      Corps du message.
	 * @param object $lead         Lead concerné.
	 * @return true|WP_Error
	 */
	abstract public function envoyer( $destinataire, $message, $lead );
}

/**
 * Meta Cloud API (WhatsApp Business).
 */
class IAM_Leads_WhatsApp_Meta extends IAM_Leads_WhatsApp_Fournisseur {

	public function id() {
		return 'meta';
	}

	public function nom() {
		return 'WhatsApp Business — Meta Cloud API';
	}

	public function envoyer( $destinataire, $message, $lead ) {
		$token    = trim( (string) IAM_Leads::get_setting( 'whatsapp_token' ) );
		$phone_id = trim( (string) IAM_Leads::get_setting( 'whatsapp_phone_id' ) );

		if ( '' === $token || '' === $phone_id ) {
			return new WP_Error( 'iam_wa_config', __( 'Jeton ou identifiant de numéro WhatsApp manquant.', 'iam-leads' ) );
		}

		$reponse = wp_remote_post(
			'https://graph.facebook.com/v20.0/' . rawurlencode( $phone_id ) . '/messages',
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $this->corps( $destinataire, $message, $lead ) ),
			)
		);

		return $this->interpreter( $reponse );
	}

	/**
	 * Construit la charge utile.
	 *
	 * Un message texte libre n'est accepté que dans les 24 h suivant le dernier
	 * message reçu du destinataire. Une notification d'équipe tombe presque
	 * toujours en dehors de cette fenêtre : le template est donc le mode normal,
	 * le texte libre ne servant qu'aux tests.
	 */
	protected function corps( $destinataire, $message, $lead ) {
		if ( 'texte' === IAM_Leads::get_setting( 'whatsapp_mode', 'template' ) ) {
			return array(
				'messaging_product' => 'whatsapp',
				'to'                => $destinataire,
				'type'              => 'text',
				'text'              => array(
					'preview_url' => false,
					'body'        => $message,
				),
			);
		}

		$parametres = array();

		foreach ( IAM_Leads_WhatsApp::parametres( $lead ) as $valeur ) {
			$parametres[] = array(
				'type' => 'text',
				'text' => $valeur,
			);
		}

		return array(
			'messaging_product' => 'whatsapp',
			'to'                => $destinataire,
			'type'              => 'template',
			'template'          => array(
				'name'       => IAM_Leads::get_setting( 'whatsapp_template', 'iam_nouveau_lead' ),
				'language'   => array( 'code' => IAM_Leads::get_setting( 'whatsapp_langue', 'fr' ) ),
				'components' => array(
					array(
						'type'       => 'body',
						'parameters' => $parametres,
					),
				),
			),
		);
	}

	/**
	 * Analyse la réponse HTTP.
	 */
	protected function interpreter( $reponse ) {
		if ( is_wp_error( $reponse ) ) {
			return $reponse;
		}

		$code = wp_remote_retrieve_response_code( $reponse );

		if ( $code >= 200 && $code < 300 ) {
			return true;
		}

		return new WP_Error(
			'iam_wa_http',
			sprintf(
				/* translators: 1 : code HTTP, 2 : corps de la réponse */
				__( 'Le fournisseur WhatsApp a répondu %1$d : %2$s', 'iam-leads' ),
				$code,
				wp_remote_retrieve_body( $reponse )
			)
		);
	}
}

/**
 * Twilio API for WhatsApp.
 */
class IAM_Leads_WhatsApp_Twilio extends IAM_Leads_WhatsApp_Meta {

	public function id() {
		return 'twilio';
	}

	public function nom() {
		return 'Twilio';
	}

	public function envoyer( $destinataire, $message, $lead ) {
		// Ici, whatsapp_phone_id sert d'Account SID et whatsapp_token de jeton d'authentification.
		$sid   = trim( (string) IAM_Leads::get_setting( 'whatsapp_phone_id' ) );
		$token = trim( (string) IAM_Leads::get_setting( 'whatsapp_token' ) );
		$from  = trim( (string) IAM_Leads::get_setting( 'whatsapp_expediteur' ) );

		if ( '' === $sid || '' === $token ) {
			return new WP_Error( 'iam_wa_config', __( 'Account SID ou jeton Twilio manquant.', 'iam-leads' ) );
		}

		$corps = array(
			'From' => 'whatsapp:' . $from,
			'To'   => 'whatsapp:' . $destinataire,
		);

		if ( 'texte' === IAM_Leads::get_setting( 'whatsapp_mode', 'template' ) ) {
			$corps['Body'] = $message;
		} else {
			// Twilio référence les templates approuvés par leur ContentSid.
			$variables = array();

			foreach ( IAM_Leads_WhatsApp::parametres( $lead ) as $index => $valeur ) {
				$variables[ (string) ( $index + 1 ) ] = $valeur;
			}

			$corps['ContentSid']       = IAM_Leads::get_setting( 'whatsapp_template' );
			$corps['ContentVariables'] = wp_json_encode( $variables );
		}

		$reponse = wp_remote_post(
			'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $sid ) . '/Messages.json',
			array(
				'timeout' => 8,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $sid . ':' . $token ), // phpcs:ignore
				),
				'body'    => $corps,
			)
		);

		return $this->interpreter( $reponse );
	}
}

/**
 * Point d'entrée des notifications.
 */
class IAM_Leads_WhatsApp {

	/**
	 * @return IAM_Leads_WhatsApp_Fournisseur[]
	 */
	public static function fournisseurs() {
		$liste = array( new IAM_Leads_WhatsApp_Meta(), new IAM_Leads_WhatsApp_Twilio() );

		return apply_filters( 'iam_leads_whatsapp_fournisseurs', $liste );
	}

	public static function fournisseur_actif() {
		$id = IAM_Leads::get_setting( 'whatsapp_fournisseur', 'meta' );

		foreach ( self::fournisseurs() as $fournisseur ) {
			if ( $fournisseur->id() === $id ) {
				return $fournisseur;
			}
		}

		return null;
	}

	/**
	 * Variables du template, dans l'ordre {{1}} à {{7}}.
	 *
	 * Meta rejette les paramètres vides et ceux contenant un saut de ligne
	 * ou une tabulation : on nettoie systématiquement.
	 *
	 * @param object $lead Lead.
	 * @return string[]
	 */
	public static function parametres( $lead ) {
		$valeurs = array(
			$lead->nom_complet,
			$lead->programme,
			$lead->telephone,
			$lead->pays,
			$lead->source,
			$lead->contact_prefere,
			mysql2date( 'd/m/Y à H:i', $lead->date_creation ),
		);

		$valeurs = array_map(
			static function ( $valeur ) {
				$valeur = preg_replace( '/[\r\n\t]+/', ' ', (string) $valeur );
				$valeur = trim( preg_replace( '/ {2,}/', ' ', $valeur ) );

				return '' === $valeur ? '—' : $valeur;
			},
			$valeurs
		);

		/**
		 * Permet d'adapter les variables si le template est modifié.
		 *
		 * @param string[] $valeurs Variables ordonnées.
		 * @param object   $lead    Lead.
		 */
		return apply_filters( 'iam_leads_whatsapp_parametres', $valeurs, $lead );
	}

	/**
	 * Corps du message envoyé à l'équipe.
	 */
	public static function composer( $lead ) {
		$statuts = IAM_Leads::statuts();

		$lignes = array(
			'🎓 Nouveau lead IAM',
			'Nom : ' . $lead->nom_complet,
			'Programme : ' . ( $lead->programme ? $lead->programme : '—' ),
			'Téléphone : ' . ( $lead->telephone ? $lead->telephone : '—' ),
			'Pays : ' . ( $lead->pays ? $lead->pays : '—' ),
			'Source : ' . ( $lead->source ? $lead->source : '—' ),
			'Contact préféré : ' . ( $lead->contact_prefere ? $lead->contact_prefere : '—' ),
			'Reçu le : ' . mysql2date( 'd/m/Y à H:i', $lead->date_creation ),
		);

		/**
		 * Permet de personnaliser le message WhatsApp.
		 *
		 * @param string $message Message.
		 * @param object $lead    Lead.
		 */
		return apply_filters( 'iam_leads_whatsapp_message', implode( "\n", $lignes ), $lead );
	}

	/**
	 * Notifie l'équipe.
	 */
	public static function notifier( $lead ) {
		if ( ! IAM_Leads::get_setting( 'whatsapp_active' ) ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'whatsapp_status', 'desactive' );
			return false;
		}

		$fournisseur = self::fournisseur_actif();

		if ( ! $fournisseur ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'whatsapp_status', 'echec' );
			IAM_Leads_Logger::ajouter( 'whatsapp', 'echec', __( 'Aucun fournisseur WhatsApp configuré.', 'iam-leads' ), $lead->id );
			return false;
		}

		$destinataires = array_filter( array_map( 'trim', explode( ',', (string) IAM_Leads::get_setting( 'whatsapp_destinataires' ) ) ) );

		if ( ! $destinataires ) {
			IAM_Leads::instance()->leads->update_envoi( $lead->id, 'whatsapp_status', 'echec' );
			IAM_Leads_Logger::ajouter( 'whatsapp', 'echec', __( 'Aucun destinataire renseigné.', 'iam-leads' ), $lead->id );
			return false;
		}

		$message = self::composer( $lead );
		$succes  = 0;

		foreach ( $destinataires as $destinataire ) {
			$resultat = $fournisseur->envoyer( $destinataire, $message, $lead );

			if ( is_wp_error( $resultat ) ) {
				IAM_Leads_Logger::ajouter( 'whatsapp', 'echec', $destinataire . ' — ' . $resultat->get_error_message(), $lead->id );
				continue;
			}

			++$succes;
		}

		$statut = $succes === count( $destinataires ) ? 'envoye' : ( $succes > 0 ? 'partiel' : 'echec' );
		IAM_Leads::instance()->leads->update_envoi( $lead->id, 'whatsapp_status', $statut );

		if ( $succes ) {
			IAM_Leads_Logger::ajouter( 'whatsapp', 'succes', sprintf( '%d/%d notification(s) envoyée(s).', $succes, count( $destinataires ) ), $lead->id );
		}

		return $succes > 0;
	}
}
