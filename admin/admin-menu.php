<?php
/**
 * Menus et pages d'administration.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Admin_Menu {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_iam_save_form', array( $this, 'sauver_formulaire' ) );
		add_action( 'admin_post_iam_delete_form', array( $this, 'supprimer_formulaire' ) );
		add_action( 'admin_post_iam_save_settings', array( $this, 'sauver_reglages' ) );
		add_action( 'admin_post_iam_update_lead', array( $this, 'maj_lead' ) );
		add_action( 'admin_post_iam_delete_lead', array( $this, 'supprimer_lead' ) );
		add_action( 'admin_post_iam_clear_log', array( $this, 'vider_journal' ) );
		add_action( 'admin_post_iam_test_email', array( $this, 'tester_email' ) );
	}

	/**
	 * Déclaration des menus.
	 */
	public function menus() {
		add_menu_page(
			__( 'IAM Leads', 'iam-leads' ),
			__( 'IAM Leads', 'iam-leads' ),
			'manage_options',
			'iam-leads-dashboard',
			array( $this, 'page_dashboard' ),
			'dashicons-groups',
			26
		);

		add_submenu_page( 'iam-leads-dashboard', __( 'Tableau de bord', 'iam-leads' ), __( 'Tableau de bord', 'iam-leads' ), 'manage_options', 'iam-leads-dashboard', array( $this, 'page_dashboard' ) );
		add_submenu_page( 'iam-leads-dashboard', __( 'Leads', 'iam-leads' ), __( 'Leads', 'iam-leads' ), 'manage_options', 'iam-leads-list', array( $this, 'page_leads' ) );
		add_submenu_page( 'iam-leads-dashboard', __( 'Formulaires', 'iam-leads' ), __( 'Formulaires', 'iam-leads' ), 'manage_options', 'iam-leads-forms', array( $this, 'page_formulaires' ) );
		add_submenu_page( 'iam-leads-dashboard', __( 'Réglages', 'iam-leads' ), __( 'Réglages', 'iam-leads' ), 'manage_options', 'iam-leads-settings', array( $this, 'page_reglages' ) );
	}

	/**
	 * Scripts et styles de l'admin.
	 */
	public function assets( $hook ) {
		if ( false === strpos( $hook, 'iam-leads' ) ) {
			return;
		}

		wp_enqueue_style( 'iam-leads-admin', IAM_LEADS_URL . 'admin/css/admin.css', array(), IAM_LEADS_VERSION );
		wp_enqueue_script( 'iam-leads-admin', IAM_LEADS_URL . 'admin/js/admin.js', array(), IAM_LEADS_VERSION, true );

		if ( false !== strpos( $hook, 'iam-leads-forms' ) ) {
			wp_enqueue_media();
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'iam-leads-builder', IAM_LEADS_URL . 'admin/js/form-builder.js', array( 'jquery', 'wp-color-picker' ), IAM_LEADS_VERSION, true );
			wp_localize_script(
				'iam-leads-builder',
				'iamBuilder',
				array(
					'types'    => IAM_Leads::types_champs(),
					'mappings' => IAM_Leads::mappings(),
					'i18n'     => array(
						'nouveauChamp'  => __( 'Nouveau champ', 'iam-leads' ),
						'labelManquant' => __( 'Ce champ n\'a pas d\'étiquette : il serait perdu à l\'enregistrement. Renseignez-la, puis enregistrez de nouveau.', 'iam-leads' ),
						'confirmer'     => __( 'Supprimer ce champ ?', 'iam-leads' ),
						'choisirPdf'    => __( 'Choisir la plaquette', 'iam-leads' ),
						'utiliser'      => __( 'Utiliser ce fichier', 'iam-leads' ),
					),
				)
			);
		}

		if ( false !== strpos( $hook, 'iam-leads-settings' ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'iam-leads-settings', IAM_LEADS_URL . 'admin/js/settings.js', array( 'jquery' ), IAM_LEADS_VERSION, true );
		}

		if ( false !== strpos( $hook, 'iam-leads-list' ) ) {
			wp_enqueue_script( 'iam-leads-export', IAM_LEADS_URL . 'admin/js/export.js', array(), IAM_LEADS_VERSION, true );
			wp_localize_script(
				'iam-leads-export',
				'iamExport',
				array(
					'aucuneColonne' => __( 'Sélectionnez au moins une colonne à exporter.', 'iam-leads' ),
				)
			);
		}

		if ( false !== strpos( $hook, 'iam-leads-dashboard' ) ) {
			/*
			 * Chart.js est servi depuis le plugin, jamais depuis un CDN :
			 * un repli distant coûtait une résolution DNS, une poignée de
			 * main TLS et 200 Ko à chaque ouverture du tableau de bord.
			 */
			if ( ! file_exists( IAM_LEADS_PATH . 'assets/lib/chart.min.js' ) ) {
				IAM_Leads_Logger::ajouter( 'dashboard', 'echec', 'Chart.js absent de assets/lib/ : les graphiques ne s\'afficheront pas.' );
				return;
			}

			wp_enqueue_script( 'iam-chartjs', IAM_LEADS_URL . 'assets/lib/chart.min.js', array(), '4.4.1', true );
			wp_enqueue_script( 'iam-leads-dashboard', IAM_LEADS_URL . 'admin/js/dashboard.js', array( 'iam-chartjs' ), IAM_LEADS_VERSION, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	public function page_dashboard() {
		$periode = sanitize_text_field( wp_unslash( $_GET['periode'] ?? '30j' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$du      = sanitize_text_field( wp_unslash( $_GET['du'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$au      = sanitize_text_field( wp_unslash( $_GET['au'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification

		$data = IAM_Leads::instance()->dashboard->payload( $periode, $du, $au );

		// Les libellés ne sont pas mis en cache avec les chiffres : la langue
		// de l'utilisateur peut différer de celle qui a rempli le cache.
		$data['i18n'] = array(
			'leads'      => __( 'Leads', 'iam-leads' ),
			'leadsMin'   => __( 'leads', 'iam-leads' ),
			'libelle'    => __( 'Libellé', 'iam-leads' ),
			'precedente' => __( 'Période précédente', 'iam-leads' ),
			'vide'       => __( 'Pas encore de données sur cette période.', 'iam-leads' ),
		);

		wp_add_inline_script( 'iam-leads-dashboard', 'window.iamDashboardData = ' . wp_json_encode( $data ) . ';', 'before' );

		require IAM_LEADS_PATH . 'admin/views/dashboard.php';
	}

	public function page_leads() {
		if ( isset( $_GET['lead'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$lead = IAM_Leads::instance()->leads->get( absint( $_GET['lead'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( $lead ) {
				require IAM_LEADS_PATH . 'admin/views/lead-detail.php';
				return;
			}
		}

		require IAM_LEADS_PATH . 'admin/views/leads-list.php';
	}

	public function page_formulaires() {
		$action = sanitize_key( $_GET['action'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( 'edit' === $action || 'new' === $action ) {
			$form = 'edit' === $action ? IAM_Leads_Form_Builder::get( absint( $_GET['id'] ?? 0 ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
			require IAM_LEADS_PATH . 'admin/views/form-edit.php';
			return;
		}

		require IAM_LEADS_PATH . 'admin/views/forms-list.php';
	}

	public function page_reglages() {
		require IAM_LEADS_PATH . 'admin/views/settings.php';
	}

	/* ---------------------------------------------------------------------
	 * Actions
	 * ------------------------------------------------------------------ */

	private function verifier( $nonce ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'iam-leads' ) );
		}
		check_admin_referer( $nonce );
	}

	public function sauver_formulaire() {
		$this->verifier( 'iam_save_form' );

		$id     = absint( $_POST['id'] ?? 0 );
		$retour = admin_url( 'admin.php?page=iam-leads-forms&action=' . ( $id ? 'edit&id=' . $id : 'new' ) );

		$champs = array();
		if ( isset( $_POST['champs'] ) && is_array( $_POST['champs'] ) ) {
			$champs = map_deep( wp_unslash( $_POST['champs'] ), 'sanitize_textarea_field' );
		}

		/*
		 * Le constructeur annonce le nombre de champs qu'il envoie. En recevoir
		 * moins signifie que PHP a coupé la requête au-delà de max_input_vars :
		 * un champ compte une quinzaine de variables, la limite est vite
		 * atteinte sur un hébergement réglé bas. Sans ce garde-fou, la
		 * définition était écrasée par une version amputée, voire vide.
		 */
		$annonces = absint( $_POST['champs_total'] ?? 0 );

		if ( count( $champs ) < $annonces ) {
			IAM_Leads_Logger::ajouter(
				'formulaire',
				'echec',
				sprintf(
					'Requête tronquée : %1$d champ(s) reçu(s) sur %2$d envoyé(s). max_input_vars = %3$s. Rien n\'a été enregistré.',
					count( $champs ),
					$annonces,
					ini_get( 'max_input_vars' )
				)
			);

			wp_safe_redirect( $retour . '&message=champs_tronques' );
			exit;
		}

		/*
		 * Filet serveur : le constructeur bloque déjà l'envoi d'un champ sans
		 * étiquette, mais sans JavaScript rien ne le retiendrait, et ces champs
		 * seraient écartés au nettoyage sans que l'écran ne le dise.
		 */
		$ignores = count( $champs ) - count( IAM_Leads_Form_Builder::sanitize_fields( $champs ) );

		$reglages = array();
		if ( isset( $_POST['reglages'] ) && is_array( $_POST['reglages'] ) ) {
			$reglages = map_deep( wp_unslash( $_POST['reglages'] ), 'sanitize_text_field' );
		}

		$enregistre = IAM_Leads_Form_Builder::save(
			array(
				'id'                   => $id,
				'nom'                  => wp_unslash( $_POST['nom'] ?? '' ),
				'champs'               => $champs,
				'reglages'             => $reglages,
				'bouton_texte'         => wp_unslash( $_POST['bouton_texte'] ?? '' ),
				'message_confirmation' => wp_unslash( $_POST['message_confirmation'] ?? '' ),
				'plaquette_pdf'        => absint( $_POST['plaquette_pdf'] ?? 0 ),
				'programme'            => wp_unslash( $_POST['programme'] ?? '' ),
			)
		);

		// Écriture refusée : la raison est déjà au journal, on ne renvoie pas
		// l'utilisateur vers un formulaire fantôme (id=0) qui semblerait vidé.
		if ( ! $enregistre ) {
			wp_safe_redirect( $retour . '&message=echec_ecriture' );
			exit;
		}

		/*
		 * Le formulaire est relu juste après l'écriture : c'est le seul compte
		 * qui reflète ce que l'écran d'édition et le shortcode afficheront.
		 * Compter les champs reçus laissait croire à un succès alors que la
		 * définition ressortait vide de la base.
		 */
		$relu = IAM_Leads_Form_Builder::get( $enregistre );

		if ( ! $relu || count( $relu->champs ) !== count( $champs ) ) {
			IAM_Leads_Logger::ajouter(
				'formulaire',
				'echec',
				sprintf(
					'Formulaire %1$d : %2$d champ(s) envoyé(s) mais %3$d relu(s) en base. Colonne « champs » : %4$d octets.',
					$enregistre,
					count( $champs ),
					$relu ? count( $relu->champs ) : 0,
					$relu ? strlen( (string) wp_json_encode( $relu->champs ) ) : 0
				)
			);
		} else {
			IAM_Leads_Logger::ajouter(
				'formulaire',
				'succes',
				sprintf( 'Formulaire %1$d enregistré et relu avec %2$d champ(s).', $enregistre, count( $relu->champs ) )
			);
		}

		wp_safe_redirect(
			admin_url( 'admin.php?page=iam-leads-forms&action=edit&id=' . $enregistre . '&message=enregistre' )
			. ( $ignores > 0 ? '&ignores=' . $ignores : '' )
		);
		exit;
	}

	public function supprimer_formulaire() {
		$this->verifier( 'iam_delete_form' );

		IAM_Leads_Form_Builder::delete( absint( $_GET['id'] ?? 0 ) );

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-forms&message=supprime' ) );
		exit;
	}

	public function maj_lead() {
		$this->verifier( 'iam_update_lead' );

		$id = absint( $_POST['id'] ?? 0 );
		IAM_Leads::instance()->leads->update_statut( $id, sanitize_key( $_POST['statut'] ?? '' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-list&lead=' . $id . '&message=maj' ) );
		exit;
	}

	public function supprimer_lead() {
		$this->verifier( 'iam_delete_lead' );

		IAM_Leads::instance()->leads->delete( absint( $_GET['id'] ?? 0 ) );

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-list&message=supprime' ) );
		exit;
	}

	/**
	 * Envoi de vérification vers les destinataires internes.
	 */
	public function tester_email() {
		$this->verifier( 'iam_test_email' );

		$destinataires = array_filter( array_map( 'trim', explode( ',', (string) IAM_Leads::get_setting( 'admin_emails' ) ) ) );

		if ( ! $destinataires ) {
			wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-settings&message=test_sans_destinataire#iam-p-emails' ) );
			exit;
		}

		$envoye = IAM_Leads_Emails::tester( $destinataires );

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-settings&message=' . ( $envoye ? 'test_envoye#iam-p-smtp' : 'test_echec#iam-p-journal' ) ) );
		exit;
	}

	public function vider_journal() {
		$this->verifier( 'iam_clear_log' );

		IAM_Leads_Logger::vider();

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-settings&message=journal#iam-p-journal' ) );
		exit;
	}

	public function sauver_reglages() {
		$this->verifier( 'iam_save_settings' );

		$plaquettes = array();
		if ( isset( $_POST['plaquettes'] ) && is_array( $_POST['plaquettes'] ) ) {
			foreach ( wp_unslash( $_POST['plaquettes'] ) as $programme => $piece ) {
				$piece = absint( $piece );
				if ( $piece ) {
					$plaquettes[ sanitize_text_field( $programme ) ] = $piece;
				}
			}
		}

		$reglages = array(
			'admin_emails'           => sanitize_text_field( wp_unslash( $_POST['admin_emails'] ?? '' ) ),
			'email_confirmation'     => empty( $_POST['email_confirmation'] ) ? 0 : 1,
			'email_expediteur_nom'   => sanitize_text_field( wp_unslash( $_POST['email_expediteur_nom'] ?? '' ) ),
			'email_expediteur_mail'  => sanitize_email( wp_unslash( $_POST['email_expediteur_mail'] ?? '' ) ),
			'smtp_active'            => empty( $_POST['smtp_active'] ) ? 0 : 1,
			'smtp_hote'              => sanitize_text_field( wp_unslash( $_POST['smtp_hote'] ?? '' ) ),
			'smtp_port'              => absint( $_POST['smtp_port'] ?? 587 ),
			'smtp_securite'          => in_array( $_POST['smtp_securite'] ?? '', array( 'tls', 'ssl', 'aucune' ), true ) ? sanitize_key( $_POST['smtp_securite'] ) : 'tls',
			'smtp_utilisateur'       => sanitize_text_field( wp_unslash( $_POST['smtp_utilisateur'] ?? '' ) ),
			// Champ laissé vide : on conserve le mot de passe déjà enregistré,
			// qui n'est jamais réaffiché dans la page.
			'smtp_motdepasse'        => '' !== ( $_POST['smtp_motdepasse'] ?? '' )
				? sanitize_text_field( wp_unslash( $_POST['smtp_motdepasse'] ) )
				: (string) IAM_Leads::get_setting( 'smtp_motdepasse' ),
			'privacy_url'            => esc_url_raw( wp_unslash( $_POST['privacy_url'] ?? '' ) ),
			'plaquettes'             => $plaquettes,
			'crm_active'             => empty( $_POST['crm_active'] ) ? 0 : 1,
			'crm_fournisseur'        => sanitize_key( $_POST['crm_fournisseur'] ?? 'hubspot' ),
			'hubspot_token'          => sanitize_text_field( wp_unslash( $_POST['hubspot_token'] ?? '' ) ),
			'whatsapp_active'        => empty( $_POST['whatsapp_active'] ) ? 0 : 1,
			'whatsapp_fournisseur'   => sanitize_key( $_POST['whatsapp_fournisseur'] ?? 'meta' ),
			'whatsapp_token'         => sanitize_text_field( wp_unslash( $_POST['whatsapp_token'] ?? '' ) ),
			'whatsapp_phone_id'      => sanitize_text_field( wp_unslash( $_POST['whatsapp_phone_id'] ?? '' ) ),
			'whatsapp_template'      => sanitize_text_field( wp_unslash( $_POST['whatsapp_template'] ?? '' ) ),
			'whatsapp_expediteur'    => sanitize_text_field( wp_unslash( $_POST['whatsapp_expediteur'] ?? '' ) ),
			'whatsapp_mode'          => 'texte' === ( $_POST['whatsapp_mode'] ?? '' ) ? 'texte' : 'template',
			'whatsapp_langue'        => sanitize_text_field( wp_unslash( $_POST['whatsapp_langue'] ?? 'fr' ) ),
			'whatsapp_destinataires' => sanitize_text_field( wp_unslash( $_POST['whatsapp_destinataires'] ?? '' ) ),
			'delai_anti_spam'        => absint( $_POST['delai_anti_spam'] ?? 3 ),
		);

		update_option( 'iam_leads_settings', wp_parse_args( $reglages, IAM_Leads::default_settings() ) );

		// On revient sur l'onglet qui était ouvert.
		$onglet = sanitize_key( wp_unslash( $_POST['_iam_onglet'] ?? '' ) );

		wp_safe_redirect( admin_url( 'admin.php?page=iam-leads-settings&message=enregistre' ) . ( $onglet ? '#' . $onglet : '' ) );
		exit;
	}

	/**
	 * En-tête commun des écrans, balisage identique à IAM Certifications
	 * (iam-adm-head) : sur-titre, titre, intro, actions à droite.
	 *
	 * @param string $sur_titre Petit libellé au-dessus du titre.
	 * @param string $titre     Titre de la page.
	 * @param string $actions   HTML des boutons d'action (déjà échappé).
	 * @param string $sous      Ligne descriptive sous le titre (optionnelle).
	 * @param string $retour    URL : le sur-titre devient un lien retour « ← ».
	 */
	public static function entete( $sur_titre, $titre, $actions = '', $sous = '', $retour = '' ) {
		?>
		<header class="iam-adm-head">
			<div>
				<p class="iam-adm-eyebrow">
					<?php if ( $retour ) : ?>
						<a href="<?php echo esc_url( $retour ); ?>">← <?php echo esc_html( $sur_titre ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $sur_titre ); ?>
					<?php endif; ?>
				</p>
				<h1 class="iam-adm-title"><?php echo esc_html( $titre ); ?></h1>
				<?php if ( $sous ) : ?>
					<p class="iam-adm-intro"><?php echo wp_kses_post( $sous ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $actions ) : ?>
				<div class="iam-adm-head__actions"><?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et échappé par l'appelant. ?></div>
			<?php endif; ?>
		</header>
		<hr class="wp-header-end">
		<?php
	}

	/**
	 * En-tête d'une section-carte (iam-sec__head) : icône, titre, description
	 * et, à droite, un contenu libre (interrupteur, lien, bouton).
	 *
	 * @param string $icone Classe dashicons sans le préfixe « dashicons- ».
	 * @param string $titre Titre de la section.
	 * @param string $desc  Description (optionnelle).
	 * @param string $aside HTML affiché à droite (déjà échappé).
	 */
	public static function sec_tete( $icone, $titre, $desc = '', $aside = '' ) {
		?>
		<div class="iam-sec__head">
			<span class="iam-sec__icon dashicons dashicons-<?php echo esc_attr( $icone ); ?>" aria-hidden="true"></span>
			<div>
				<h2 class="iam-sec__title"><?php echo esc_html( $titre ); ?></h2>
				<?php if ( $desc ) : ?>
					<p class="iam-sec__desc"><?php echo esc_html( $desc ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( $aside ) : ?>
				<div class="iam-sec__aside"><?php echo $aside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML construit et échappé par l'appelant. ?></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Interrupteur (même composant que IAM Certifications).
	 *
	 * @param string $nom     Attribut name de la case.
	 * @param bool   $coche   État.
	 * @param string $libelle Libellé.
	 * @param string $aide    Ligne d'aide sous le libellé (optionnelle).
	 * @return string HTML.
	 */
	public static function interrupteur( $nom, $coche, $libelle, $aide = '' ) {
		return sprintf(
			'<label class="iam-switch"><input type="checkbox" name="%1$s" value="1"%2$s><span class="iam-switch__track" aria-hidden="true"></span><span>%3$s%4$s</span></label>',
			esc_attr( $nom ),
			$coche ? ' checked' : '',
			esc_html( $libelle ),
			$aide ? '<small>' . esc_html( $aide ) . '</small>' : ''
		);
	}

	/**
	 * Affiche un message d'état standard.
	 */
	public static function notice() {
		$message = sanitize_key( $_GET['message'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		$ignores = absint( $_GET['ignores'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( $ignores > 0 ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d : nombre de champs écartés */
						_n(
							'%d champ sans étiquette a été ignoré : il n\'a pas été enregistré.',
							'%d champs sans étiquette ont été ignorés : ils n\'ont pas été enregistrés.',
							$ignores,
							'iam-leads'
						),
						$ignores
					)
				)
			);
		}

		$erreurs = array(
			'export_vide'      => __( 'Aucune colonne sélectionnée : l\'export a été annulé.', 'iam-leads' ),
			'export_echec'     => __( 'Le fichier Excel n\'a pas pu être créé. Réglages → Journal en donne la raison.', 'iam-leads' ),
			'test_echec'             => __( 'L\'e-mail de test n\'est pas parti. L\'onglet Journal indique le motif exact renvoyé par le serveur d\'envoi.', 'iam-leads' ),
			'test_sans_destinataire' => __( 'Aucun destinataire interne n\'est renseigné : il n\'y a personne à qui envoyer le test.', 'iam-leads' ),
			'champs_tronques'  => __( 'Le serveur n\'a reçu qu\'une partie des champs : la limite PHP « max_input_vars » est trop basse. Rien n\'a été enregistré. Faites-la passer à 5000, puis recommencez. Réglages → Journal indique la valeur actuelle.', 'iam-leads' ),
			'echec_ecriture'   => __( 'La base de données a refusé l\'enregistrement : rien n\'a été modifié. Réglages → Journal indique l\'erreur SQL exacte.', 'iam-leads' ),
		);

		if ( isset( $erreurs[ $message ] ) ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $erreurs[ $message ] )
			);

			return;
		}

		$textes = array(
			'enregistre' => __( 'Modifications enregistrées.', 'iam-leads' ),
			'supprime'   => __( 'Élément supprimé.', 'iam-leads' ),
			'maj'        => __( 'Lead mis à jour.', 'iam-leads' ),
			'journal'     => __( 'Journal vidé.', 'iam-leads' ),
			'test_envoye' => __( 'E-mail de test expédié. S\'il n\'arrive pas, vérifiez le dossier indésirables puis les enregistrements SPF/DKIM du domaine expéditeur.', 'iam-leads' ),
		);

		if ( isset( $textes[ $message ] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $textes[ $message ] )
			);
		}
	}
}

if ( ! function_exists( 'iam_leads_initiales' ) ) {
	/**
	 * Initiales d'un nom pour la pastille (avatar) des listes.
	 *
	 * @param string $nom Nom complet.
	 * @return string Une ou deux lettres, « ? » si le nom est vide.
	 */
	function iam_leads_initiales( $nom ) {
		$mots = preg_split( '/\s+/u', trim( (string) $nom ), -1, PREG_SPLIT_NO_EMPTY );

		if ( ! $mots ) {
			return '?';
		}

		$initiales = '';
		foreach ( array( reset( $mots ), count( $mots ) > 1 ? end( $mots ) : '' ) as $mot ) {
			if ( '' !== $mot ) {
				$initiales .= function_exists( 'mb_substr' ) ? mb_substr( $mot, 0, 1 ) : substr( $mot, 0, 1 );
			}
		}

		return $initiales;
	}
}
