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
	 * Mode diagnostic (e-mail de test) : la conversation avec le serveur
	 * SMTP est conservée pour être écrite au journal en cas d'échec.
	 *
	 * @var bool
	 */
	private static $diagnostic = false;

	/**
	 * Lignes de la conversation SMTP (mode diagnostic).
	 *
	 * @var string[]
	 */
	private static $trace = array();

	/**
	 * Réglages SMTP (Réglages → Serveur d'envoi). La constante
	 * IAM_LEADS_SMTP_PASS de wp-config.php prime sur le mot de passe stocké.
	 *
	 * @return array{actif:bool,hote:string,port:int,securite:string,utilisateur:string,motdepasse:string}
	 */
	private static function config_smtp() {
		$hote = trim( (string) IAM_Leads::get_setting( 'smtp_hote' ) );

		return array(
			'actif'       => (bool) IAM_Leads::get_setting( 'smtp_active' ) && '' !== $hote,
			'hote'        => $hote,
			'port'        => (int) IAM_Leads::get_setting( 'smtp_port', 587 ),
			'securite'    => (string) IAM_Leads::get_setting( 'smtp_securite', 'tls' ),
			'utilisateur' => trim( (string) IAM_Leads::get_setting( 'smtp_utilisateur' ) ),
			'motdepasse'  => defined( 'IAM_LEADS_SMTP_PASS' ) && IAM_LEADS_SMTP_PASS ? (string) IAM_LEADS_SMTP_PASS : (string) IAM_Leads::get_setting( 'smtp_motdepasse' ),
		);
	}

	/**
	 * Bascule PHPMailer sur un serveur SMTP.
	 * Branché sur phpmailer_init : s'applique donc à tous les e-mails du site,
	 * pas seulement à ceux du plugin.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Instance fournie par WordPress.
	 */
	public static function configurer_smtp( $phpmailer ) {
		$config = self::config_smtp();

		if ( ! $config['actif'] ) {
			return;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host        = $config['hote'];
		$phpmailer->Port        = $config['port'] ? $config['port'] : 587;
		$phpmailer->SMTPSecure  = in_array( $config['securite'], array( 'tls', 'ssl' ), true ) ? $config['securite'] : '';
		$phpmailer->SMTPAutoTLS = 'aucune' !== $config['securite'];
		$phpmailer->Timeout     = 20;

		if ( '' !== $config['utilisateur'] ) {
			$phpmailer->SMTPAuth = true;
			$phpmailer->Username = $config['utilisateur'];
			$phpmailer->Password = $config['motdepasse'];

			/*
			 * Enveloppe (Return-Path) au nom du compte authentifié : beaucoup
			 * de serveurs refusent un message dont l'expéditeur technique
			 * n'est pas le compte connecté (« Sender address rejected »).
			 */
			if ( is_email( $config['utilisateur'] ) ) {
				$phpmailer->Sender = $config['utilisateur'];
			}
		}

		if ( self::$diagnostic ) {
			// Niveau 2 : réponses du serveur et commandes du client. Les
			// commandes du client sont écartées : elles portent les
			// identifiants encodés pendant l'authentification.
			$phpmailer->SMTPDebug   = 2;
			$phpmailer->Debugoutput = static function ( $ligne ) {
				$ligne = trim( (string) $ligne );
				if ( '' !== $ligne && false === strpos( $ligne, 'CLIENT -> SERVER' ) ) {
					self::$trace[] = $ligne;
				}
			};
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

		self::$diagnostic = true;
		self::$trace      = array();

		$config = self::config_smtp();
		$envoye = self::envoyer(
			$destinataire,
			__( '[IAM] Test d\'envoi', 'iam-leads' ),
			$corps,
			__( 'E-mail de test', 'iam-leads' )
		);

		self::$diagnostic = false;

		IAM_Leads_Logger::ajouter(
			'email',
			$envoye ? 'succes' : 'echec',
			sprintf(
				'Test SMTP — serveur : %1$s:%2$d (%3$s) ; compte : %4$s. %5$s',
				$config['actif'] ? $config['hote'] : '(aucun, envoi PHP mail())',
				$config['port'],
				$config['securite'],
				'' !== $config['utilisateur'] ? $config['utilisateur'] : 'sans authentification',
				self::$trace ? 'Réponses du serveur : ' . implode( ' | ', array_slice( self::$trace, -12 ) ) : ''
			)
		);

		self::$trace = array();

		return $envoye;
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
	 * Destinataires de la notification interne d'un formulaire : les siens
	 * s'il en a (plus ceux par défaut si « copie » est cochée), sinon ceux
	 * par défaut de Réglages → E-mails.
	 *
	 * @param object|null $form Formulaire.
	 * @return string[]
	 */
	public static function destinataires( $form ) {
		$defaut = IAM_Leads_Form_Builder::adresses( IAM_Leads::get_setting( 'admin_emails' ) );
		$propre = $form && ! empty( $form->reglages['destinataires'] ) ? IAM_Leads_Form_Builder::adresses( $form->reglages['destinataires'] ) : array();

		if ( ! $propre ) {
			return $defaut;
		}

		return ! empty( $form->reglages['copie_defaut'] ) ? array_values( array_unique( array_merge( $propre, $defaut ) ) ) : $propre;
	}

	/**
	 * Notification à l'équipe IAM.
	 */
	public static function notifier_admin( $lead, $form ) {
		$destinataires = self::destinataires( $form );

		if ( ! $destinataires ) {
			IAM_Leads_Logger::ajouter(
				'email',
				'echec',
				__( 'Notification interne ignorée : ni le formulaire ni Réglages → E-mails n\'ont de destinataire.', 'iam-leads' ),
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
