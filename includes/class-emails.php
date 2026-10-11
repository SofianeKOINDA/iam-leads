<?php
/**
 * E-mails de notification et de confirmation.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Emails {

	/**
	 * Envoi centralisé, tracé au journal.
	 *
	 * wp_mail() ne rend qu'un booléen : sans cette capture de wp_mail_failed,
	 * un serveur dépourvu de service d'envoi — le cas d'un conteneur Docker,
	 * qui n'embarque aucun sendmail — échouait sans laisser la moindre trace.
	 *
	 * @param string|array $destinataires Destinataire(s).
	 * @param string       $sujet         Objet.
	 * @param string       $corps         Corps HTML.
	 * @param string       $etiquette     Intitulé pour le journal.
	 * @param int          $lead_id       Lead concerné.
	 * @return bool
	 */
	private static function envoyer( $destinataires, $sujet, $corps, $etiquette, $lead_id = 0 ) {
		$detail = '';

		$capture = static function ( $erreur ) use ( &$detail ) {
			$detail = $erreur->get_error_message();
		};

		add_action( 'wp_mail_failed', $capture );
		$envoye = wp_mail( $destinataires, $sujet, $corps, self::entetes() );
		remove_action( 'wp_mail_failed', $capture );

		$adresses = implode( ', ', (array) $destinataires );

		if ( $envoye ) {
			IAM_Leads_Logger::ajouter( 'email', 'succes', sprintf( '%1$s envoyé à %2$s.', $etiquette, $adresses ), $lead_id );

			return true;
		}

		if ( '' === $detail ) {
			$detail = __( 'wp_mail() a échoué sans message. Aucun service d\'envoi n\'est joignable : configurez le SMTP dans Réglages.', 'iam-leads' );
		}

		IAM_Leads_Logger::ajouter(
			'email',
			'echec',
			sprintf( '%1$s NON envoyé à %2$s. %3$s', $etiquette, $adresses, $detail ),
			$lead_id
		);

		return false;
	}

	/**
	 * Mot de passe SMTP.
	 * La constante, à poser dans wp-config.php, prime sur la valeur stockée en
	 * base : elle permet de garder le secret hors de la table des options.
	 */
	private static function mot_de_passe_smtp() {
		if ( defined( 'IAM_LEADS_SMTP_PASS' ) && IAM_LEADS_SMTP_PASS ) {
			return (string) IAM_LEADS_SMTP_PASS;
		}

		return (string) IAM_Leads::get_setting( 'smtp_motdepasse' );
	}

	/**
	 * Bascule PHPMailer sur un serveur SMTP.
	 * Branché sur phpmailer_init : s'applique donc à tous les e-mails du site,
	 * pas seulement à ceux du plugin.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instance fournie par WordPress.
	 */
	public static function configurer_smtp( $phpmailer ) {
		if ( ! IAM_Leads::get_setting( 'smtp_active' ) ) {
			return;
		}

		$hote = trim( (string) IAM_Leads::get_setting( 'smtp_hote' ) );

		if ( '' === $hote ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host = $hote;
		$phpmailer->Port = (int) IAM_Leads::get_setting( 'smtp_port', 587 );

		$securite               = IAM_Leads::get_setting( 'smtp_securite', 'tls' );
		$phpmailer->SMTPSecure  = in_array( $securite, array( 'tls', 'ssl' ), true ) ? $securite : '';
		$phpmailer->SMTPAutoTLS = 'aucune' !== $securite;

		$utilisateur = trim( (string) IAM_Leads::get_setting( 'smtp_utilisateur' ) );

		if ( '' !== $utilisateur ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $utilisateur;
			$phpmailer->Password = self::mot_de_passe_smtp();
		}
	}

	/**
	 * Message de vérification, déclenché depuis les réglages.
	 *
	 * @param string $destinataire Adresse à tester.
	 * @return bool
	 */
	public static function tester( $destinataire ) {
		$corps  = '<p style="font-family:Arial,sans-serif;font-size:15px;">';
		$corps .= esc_html__( 'Cet e-mail confirme que l\'envoi fonctionne depuis votre site.', 'iam-leads' );
		$corps .= '</p><p style="font-family:Arial,sans-serif;font-size:13px;color:#555;">';
		$corps .= esc_html( sprintf( 'IAM Leads %1$s — %2$s', IAM_LEADS_VERSION, home_url() ) );
		$corps .= '</p>';

		return self::envoyer(
			$destinataire,
			__( '[IAM] Test d\'envoi', 'iam-leads' ),
			$corps,
			__( 'E-mail de test', 'iam-leads' )
		);
	}

	/**
	 * En-têtes communs.
	 */
	private static function entetes() {
		$nom  = IAM_Leads::get_setting( 'email_expediteur_nom', 'Groupe IAM' );
		$mail = IAM_Leads::get_setting( 'email_expediteur_mail', get_option( 'admin_email' ) );

		return array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $nom, $mail ),
		);
	}

	/**
	 * Table HTML des réponses.
	 */
	private static function tableau_reponses( $lead ) {
		$reponses = json_decode( (string) $lead->donnees, true );

		if ( ! is_array( $reponses ) ) {
			return '';
		}

		$html = '<table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">';

		foreach ( $reponses as $champ ) {
			if ( '' === ( $champ['valeur'] ?? '' ) ) {
				continue;
			}
			$html .= sprintf(
				'<tr><td style="border-bottom:1px solid #e5e5e5;"><strong>%s</strong></td><td style="border-bottom:1px solid #e5e5e5;">%s</td></tr>',
				esc_html( $champ['label'] ),
				esc_html( $champ['valeur'] )
			);
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Notification à l'équipe IAM.
	 */
	public static function notifier_admin( $lead, $form ) {
		$destinataires = array_filter( array_map( 'trim', explode( ',', (string) IAM_Leads::get_setting( 'admin_emails' ) ) ) );

		if ( ! $destinataires ) {
			IAM_Leads_Logger::ajouter(
				'email',
				'echec',
				__( 'Notification interne ignorée : aucun destinataire dans Réglages → Destinataires internes.', 'iam-leads' ),
				(int) $lead->id
			);

			return false;
		}

		$sujet = sprintf(
			/* translators: 1 : nom du prospect, 2 : programme */
			__( '[IAM] Nouveau lead : %1$s — %2$s', 'iam-leads' ),
			$lead->nom_complet ? $lead->nom_complet : __( 'sans nom', 'iam-leads' ),
			$lead->programme ? $lead->programme : __( 'programme non précisé', 'iam-leads' )
		);

		$lien = admin_url( 'admin.php?page=iam-leads-list&lead=' . (int) $lead->id );

		$corps  = '<p>' . esc_html__( 'Une nouvelle demande vient d\'être reçue.', 'iam-leads' ) . '</p>';
		$corps .= self::tableau_reponses( $lead );
		$corps .= '<p style="margin-top:16px;font-family:Arial,sans-serif;font-size:13px;color:#555;">';
		$corps .= esc_html__( 'Origine : ', 'iam-leads' ) . esc_html( $lead->utm_source ? $lead->utm_source : __( 'directe', 'iam-leads' ) );
		$corps .= $lead->utm_campaign ? ' — ' . esc_html( $lead->utm_campaign ) : '';
		$corps .= '<br>' . esc_html__( 'Page du formulaire : ', 'iam-leads' ) . esc_html( $lead->page_soumission );
		$corps .= '</p>';
		$corps .= sprintf( '<p><a href="%s">%s</a></p>', esc_url( $lien ), esc_html__( 'Ouvrir la fiche du lead', 'iam-leads' ) );

		return self::envoyer( $destinataires, $sujet, $corps, __( 'Notification interne', 'iam-leads' ), (int) $lead->id );
	}

	/**
	 * Accusé de réception au visiteur.
	 */
	public static function confirmer_visiteur( $lead, $form ) {
		if ( ! IAM_Leads::get_setting( 'email_confirmation' ) ) {
			IAM_Leads_Logger::ajouter(
				'email',
				'info',
				__( 'Accusé de réception ignoré : l\'option est décochée dans Réglages.', 'iam-leads' ),
				(int) $lead->id
			);

			return false;
		}

		/*
		 * Sans champ de type « E-mail » relié à la correspondance CRM « E-mail »,
		 * la colonne reste vide et le prospect n'est joignable par aucun moyen.
		 */
		if ( ! is_email( $lead->email ) ) {
			IAM_Leads_Logger::ajouter(
				'email',
				'echec',
				__( 'Accusé de réception impossible : le lead n\'a pas d\'adresse e-mail. Vérifiez qu\'un champ du formulaire porte bien la correspondance CRM « E-mail ».', 'iam-leads' ),
				(int) $lead->id
			);

			return false;
		}

		$sujet = __( 'Votre demande d\'information — Groupe IAM', 'iam-leads' );

		$corps  = '<p style="font-family:Arial,sans-serif;font-size:15px;">';
		$corps .= esc_html__( 'Bonjour', 'iam-leads' ) . ( $lead->nom_complet ? ' ' . esc_html( $lead->nom_complet ) : '' ) . ',</p>';
		$corps .= '<p style="font-family:Arial,sans-serif;font-size:15px;">';
		$corps .= wp_kses_post( $form->message_confirmation ? $form->message_confirmation : __( 'Nous avons bien reçu votre demande.', 'iam-leads' ) );
		$corps .= '</p>';
		$corps .= '<p style="font-family:Arial,sans-serif;font-size:15px;">' . esc_html__( 'Récapitulatif de votre demande :', 'iam-leads' ) . '</p>';
		$corps .= self::tableau_reponses( $lead );
		$corps .= '<p style="font-family:Arial,sans-serif;font-size:13px;color:#555;margin-top:20px;">';
		$corps .= 'Groupe IAM — Institut Africain de Management<br>';
		$corps .= '+221 33 869 36 36 / +221 77 698 39 66<br>';
		$corps .= 'info@groupeiam.com — www.groupeiam.com';
		$corps .= '</p>';

		return self::envoyer( $lead->email, $sujet, $corps, __( 'Accusé de réception', 'iam-leads' ), (int) $lead->id );
	}
}
