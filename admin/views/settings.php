<?php
/**
 * Réglages du plugin.
 *
 * Une section par onglet (même composant que les onglets de IAM
 * Certifications) : la page tient sur un écran au lieu d'une longue
 * colonne de tableaux WordPress. Sans JavaScript, tout reste affiché.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reglages   = IAM_Leads::get_settings();
$plaquettes = (array) $reglages['plaquettes'];
$journal    = IAM_Leads_Logger::lire( 30 );
$echecs     = count(
	array_filter(
		$journal,
		static function ( $ligne ) {
			return 'echec' === $ligne['niveau'];
		}
	)
);

$onglets = array(
	'iam-p-emails'     => array( 'email-alt', __( 'E-mails', 'iam-leads' ) ),
	'iam-p-smtp'       => array( 'admin-network', __( 'Serveur d\'envoi', 'iam-leads' ) ),
	'iam-p-plaquettes' => array( 'media-document', __( 'Plaquettes', 'iam-leads' ) ),
	'iam-p-crm'        => array( 'database', __( 'CRM', 'iam-leads' ) ),
	'iam-p-whatsapp'   => array( 'whatsapp', __( 'WhatsApp', 'iam-leads' ) ),
	'iam-p-journal'    => array( 'list-view', __( 'Journal', 'iam-leads' ) ),
);

$m = 'IAM_Leads_Admin_Menu';
?>
<div class="wrap iam-wrap">
	<?php IAM_Leads_Admin_Menu::entete( __( 'Configuration', 'iam-leads' ), __( 'Réglages', 'iam-leads' ), '', __( 'Notifications, envoi des e-mails, plaquettes, CRM et WhatsApp.', 'iam-leads' ) ); ?>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="iam_save_settings">
		<input type="hidden" name="_iam_onglet" value="">
		<?php wp_nonce_field( 'iam_save_settings' ); ?>

		<div class="iam-adm-tabs" role="tablist" data-iam-onglets aria-label="<?php esc_attr_e( 'Sections des réglages', 'iam-leads' ); ?>">
			<?php foreach ( $onglets as $cible => $onglet ) : ?>
				<button type="button" role="tab" id="<?php echo esc_attr( $cible ); ?>-tab" aria-controls="<?php echo esc_attr( $cible ); ?>" aria-selected="false">
					<span class="dashicons dashicons-<?php echo esc_attr( $onglet[0] ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $onglet[1] ); ?>
					<?php if ( 'iam-p-journal' === $cible && $echecs ) : ?>
						<span title="<?php esc_attr_e( 'Envois en échec', 'iam-leads' ); ?>"><?php echo esc_html( $echecs ); ?></span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>

		<!-- E-mails ------------------------------------------------------- -->
		<section class="iam-sec" id="iam-p-emails" role="tabpanel" aria-labelledby="iam-p-emails-tab">
			<?php $m::sec_tete( 'email-alt', __( 'Notifications par e-mail', 'iam-leads' ), __( 'Qui est prévenu à chaque nouveau lead, et ce que reçoit le prospect.', 'iam-leads' ) ); ?>
			<div class="iam-sec__body">
				<div class="iam-fields">
					<div class="iam-field iam-field--full">
						<label class="iam-field__label" for="admin_emails"><?php esc_html_e( 'Destinataires par défaut', 'iam-leads' ); ?></label>
						<input type="text" id="admin_emails" name="admin_emails" value="<?php echo esc_attr( $reglages['admin_emails'] ); ?>">
						<p class="iam-field__hint"><?php esc_html_e( 'Prévenus pour les formulaires qui n\'ont pas leurs propres destinataires (Formulaires → Modifier → Qui reçoit les leads). Plusieurs adresses : séparez-les par des virgules.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="email_expediteur_nom"><?php esc_html_e( 'Nom de l\'expéditeur', 'iam-leads' ); ?></label>
						<input type="text" id="email_expediteur_nom" name="email_expediteur_nom" value="<?php echo esc_attr( $reglages['email_expediteur_nom'] ); ?>">
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="email_expediteur_mail"><?php esc_html_e( 'Adresse d\'expédition', 'iam-leads' ); ?></label>
						<input type="email" id="email_expediteur_mail" name="email_expediteur_mail" value="<?php echo esc_attr( $reglages['email_expediteur_mail'] ); ?>">
						<p class="iam-field__hint"><?php esc_html_e( 'Une adresse du domaine géré chez Systalink évite le classement en indésirable.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="privacy_url"><?php esc_html_e( 'Politique de confidentialité', 'iam-leads' ); ?></label>
						<input type="url" id="privacy_url" name="privacy_url" value="<?php echo esc_attr( $reglages['privacy_url'] ); ?>" placeholder="https://">
						<p class="iam-field__hint"><?php esc_html_e( 'Le lien s\'affiche à côté de la case de consentement.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="delai_anti_spam"><?php esc_html_e( 'Délai anti-robot', 'iam-leads' ); ?></label>
						<div class="iam-field__suffix">
							<input type="number" id="delai_anti_spam" name="delai_anti_spam" min="0" max="30" value="<?php echo esc_attr( $reglages['delai_anti_spam'] ); ?>">
							<?php esc_html_e( 'secondes minimum entre l\'affichage et l\'envoi', 'iam-leads' ); ?>
						</div>
					</div>
				</div>
				<?php echo $m::interrupteur( 'email_confirmation', (bool) $reglages['email_confirmation'], __( 'Envoyer un accusé de réception au prospect', 'iam-leads' ), __( 'Avec le lien vers la plaquette du programme demandé, s\'il y en a une.', 'iam-leads' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</section>

		<!-- SMTP ---------------------------------------------------------- -->
		<section class="iam-sec" id="iam-p-smtp" role="tabpanel" aria-labelledby="iam-p-smtp-tab">
			<?php
			$m::sec_tete(
				'admin-network',
				__( 'Serveur d\'envoi (SMTP)', 'iam-leads' ),
				__( 'Sans SMTP, WordPress ne peut expédier aucun e-mail : ni notification interne, ni accusé de réception.', 'iam-leads' ),
				$m::interrupteur( 'smtp_active', (bool) $reglages['smtp_active'], __( 'Activé', 'iam-leads' ) )
			);
			?>
			<div class="iam-sec__body">
				<div class="iam-fields">
					<div class="iam-field">
						<label class="iam-field__label" for="smtp_hote"><?php esc_html_e( 'Serveur', 'iam-leads' ); ?></label>
						<input type="text" id="smtp_hote" name="smtp_hote" value="<?php echo esc_attr( $reglages['smtp_hote'] ); ?>" placeholder="smtp.gmail.com">
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="smtp_port"><?php esc_html_e( 'Port et chiffrement', 'iam-leads' ); ?></label>
						<div class="iam-field__row">
							<input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" value="<?php echo esc_attr( $reglages['smtp_port'] ); ?>" aria-label="<?php esc_attr_e( 'Port', 'iam-leads' ); ?>">
							<select name="smtp_securite" aria-label="<?php esc_attr_e( 'Chiffrement', 'iam-leads' ); ?>">
								<option value="tls" <?php selected( $reglages['smtp_securite'], 'tls' ); ?>><?php esc_html_e( 'STARTTLS (port 587)', 'iam-leads' ); ?></option>
								<option value="ssl" <?php selected( $reglages['smtp_securite'], 'ssl' ); ?>><?php esc_html_e( 'SSL/TLS (port 465)', 'iam-leads' ); ?></option>
								<option value="aucune" <?php selected( $reglages['smtp_securite'], 'aucune' ); ?>><?php esc_html_e( 'Aucun chiffrement (port 25)', 'iam-leads' ); ?></option>
							</select>
						</div>
						<p class="iam-field__hint"><?php esc_html_e( 'Le port et le chiffrement vont ensemble : 587 + STARTTLS, ou 465 + SSL/TLS.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="smtp_utilisateur"><?php esc_html_e( 'Identifiant', 'iam-leads' ); ?></label>
						<input type="text" id="smtp_utilisateur" name="smtp_utilisateur" value="<?php echo esc_attr( $reglages['smtp_utilisateur'] ); ?>" autocomplete="off" placeholder="communications@groupeiam.com">
						<p class="iam-field__hint"><?php esc_html_e( 'Le plus souvent l\'adresse complète. Vide = envoi sans authentification.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="smtp_motdepasse"><?php esc_html_e( 'Mot de passe', 'iam-leads' ); ?></label>
						<?php if ( defined( 'IAM_LEADS_SMTP_PASS' ) && IAM_LEADS_SMTP_PASS ) : ?>
							<p class="iam-field__note">
								<span class="dashicons dashicons-lock" aria-hidden="true"></span>
								<?php esc_html_e( 'Défini dans wp-config.php (IAM_LEADS_SMTP_PASS).', 'iam-leads' ); ?>
							</p>
						<?php else : ?>
							<input type="password" id="smtp_motdepasse" name="smtp_motdepasse" value="" autocomplete="new-password"
								placeholder="<?php echo $reglages['smtp_motdepasse'] ? esc_attr__( '•••••••• (enregistré)', 'iam-leads' ) : ''; ?>">
							<p class="iam-field__hint"><?php esc_html_e( 'Laissez vide pour garder le mot de passe actuel. Gmail / Google Workspace : un mot de passe d\'application (16 lettres), pas le mot de passe du compte.', 'iam-leads' ); ?></p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="iam-sec__foot">
				<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_test_email' ), 'iam_test_email' ) ); ?>">
					<span class="dashicons dashicons-email" aria-hidden="true"></span>
					<?php esc_html_e( 'Envoyer un e-mail de test', 'iam-leads' ); ?>
				</a>
				<span><?php esc_html_e( 'Enregistrez d\'abord. Le test part vers les destinataires par défaut ; en cas d\'échec, la réponse exacte du serveur s\'inscrit dans le journal.', 'iam-leads' ); ?></span>
			</div>
		</section>

		<!-- Plaquettes ---------------------------------------------------- -->
		<section class="iam-sec" id="iam-p-plaquettes" role="tabpanel" aria-labelledby="iam-p-plaquettes-tab">
			<?php $m::sec_tete( 'media-document', __( 'Plaquettes par programme', 'iam-leads' ), __( 'Le lien proposé après l\'envoi dépend du programme choisi. Sans plaquette, le message « Notre équipe vous contactera sous 48h » s\'affiche.', 'iam-leads' ) ); ?>
			<ul class="iam-plaquettes">
				<?php foreach ( IAM_Leads_Form_Builder::programmes() as $programme ) : ?>
					<?php
					$piece_id = absint( $plaquettes[ $programme ] ?? 0 );
					$url      = $piece_id ? wp_get_attachment_url( $piece_id ) : '';
					?>
					<li class="iam-plaquette-ligne">
						<span class="iam-plaquette-programme"><?php echo esc_html( $programme ); ?></span>
						<input type="hidden" class="iam-plaquette-id" name="plaquettes[<?php echo esc_attr( $programme ); ?>]" value="<?php echo esc_attr( $piece_id ); ?>">
						<span class="iam-fichier iam-plaquette-nom"><?php echo $url ? esc_html( basename( $url ) ) : esc_html__( 'Aucun fichier', 'iam-leads' ); ?></span>
						<span class="iam-plaquette-actions">
							<button type="button" class="button iam-plaquette-choisir"><?php esc_html_e( 'Choisir un PDF', 'iam-leads' ); ?></button>
							<button type="button" class="iam-adm-icon iam-adm-icon--danger iam-plaquette-retirer" title="<?php esc_attr_e( 'Retirer', 'iam-leads' ); ?>">
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></span>
							</button>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<!-- CRM ----------------------------------------------------------- -->
		<?php
		$crm_choisi = $reglages['crm_fournisseur'] ?? 'iam_crm';
		$crm_test   = (array) get_option( 'iam_leads_crm_test', array() );
		$crm_etats  = IAM_Leads_CRM_Integration::compteurs();
		$crm_icones = array(
			'iam_crm' => 'building',
			'hubspot' => 'networking',
		);
		$crm_aides  = array(
			'iam_crm' => __( 'Le CRM interne du Groupe IAM.', 'iam-leads' ),
			'hubspot' => __( 'Un compte HubSpot (application privée).', 'iam-leads' ),
		);
		if ( empty( $crm_test ) ) {
			$crm_pastille = '<span class="iam-adm-pill iam-adm-pill--neutre">' . esc_html__( 'Connexion non testée', 'iam-leads' ) . '</span>';
		} elseif ( ! empty( $crm_test['ok'] ) ) {
			$crm_pastille = '<span class="iam-badge iam-badge-succes">' . esc_html__( 'Connecté', 'iam-leads' ) . '</span>';
		} else {
			$crm_pastille = '<span class="iam-badge iam-badge-echec">' . esc_html__( 'Échec de connexion', 'iam-leads' ) . '</span>';
		}
		?>
		<section class="iam-sec" id="iam-p-crm" role="tabpanel" aria-labelledby="iam-p-crm-tab">
			<?php
			$m::sec_tete(
				'database',
				__( 'Connexion au CRM', 'iam-leads' ),
				__( 'Chaque lead est envoyé au CRM dès sa réception. Une personne déjà connue du CRM n\'est jamais dupliquée.', 'iam-leads' ),
				$crm_pastille . $m::interrupteur( 'crm_active', (bool) $reglages['crm_active'], __( 'Activé', 'iam-leads' ) )
			);
			?>
			<div class="iam-sec__body" data-iam-crm>

				<fieldset class="iam-options">
					<legend class="iam-field__label"><?php esc_html_e( 'CRM utilisé', 'iam-leads' ); ?></legend>
					<?php foreach ( IAM_Leads_CRM_Integration::fournisseurs() as $fournisseur ) : ?>
						<label class="iam-options__carte">
							<input type="radio" name="crm_fournisseur" value="<?php echo esc_attr( $fournisseur->id() ); ?>" <?php checked( $crm_choisi, $fournisseur->id() ); ?>>
							<span class="dashicons dashicons-<?php echo esc_attr( $crm_icones[ $fournisseur->id() ] ?? 'database' ); ?>" aria-hidden="true"></span>
							<strong><?php echo esc_html( $fournisseur->nom() ); ?></strong>
							<small><?php echo esc_html( $crm_aides[ $fournisseur->id() ] ?? '' ); ?></small>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<div class="iam-fields" data-crm-pour="iam_crm">
					<div class="iam-field">
						<label class="iam-field__label" for="iam_crm_url"><?php esc_html_e( 'Adresse du CRM', 'iam-leads' ); ?></label>
						<input type="url" id="iam_crm_url" name="iam_crm_url" placeholder="https://crm.groupeiam.com" value="<?php echo esc_attr( $reglages['iam_crm_url'] ?? '' ); ?>">
						<p class="iam-field__hint">
							<?php if ( IAM_Leads_CRM_IamCrm::endpoint() ) : ?>
								<?php esc_html_e( 'Les leads partent vers :', 'iam-leads' ); ?> <code><?php echo esc_html( IAM_Leads_CRM_IamCrm::endpoint() ); ?></code>
							<?php else : ?>
								<?php esc_html_e( 'L\'adresse du CRM, sans chemin : /api/integrations/iam-leads est ajouté automatiquement.', 'iam-leads' ); ?>
							<?php endif; ?>
						</p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="iam_crm_token"><?php esc_html_e( 'Clé API', 'iam-leads' ); ?></label>
						<input type="password" id="iam_crm_token" name="iam_crm_token" autocomplete="new-password" placeholder="<?php echo ! empty( $reglages['iam_crm_token'] ) ? esc_attr__( '•••••••• (enregistrée)', 'iam-leads' ) : ''; ?>" value="">
						<p class="iam-field__hint"><?php esc_html_e( 'La valeur de IAM_LEADS_API_KEY sur le serveur du CRM. Laissez vide pour garder la clé enregistrée.', 'iam-leads' ); ?></p>
					</div>
				</div>

				<div class="iam-fields" data-crm-pour="hubspot">
					<div class="iam-field iam-field--full">
						<label class="iam-field__label" for="hubspot_token"><?php esc_html_e( 'Jeton HubSpot', 'iam-leads' ); ?></label>
						<input type="password" id="hubspot_token" name="hubspot_token" autocomplete="new-password" value="<?php echo esc_attr( $reglages['hubspot_token'] ); ?>">
						<p class="iam-field__hint"><?php esc_html_e( 'Private App Token avec le droit crm.objects.contacts.write.', 'iam-leads' ); ?></p>
					</div>
				</div>

				<?php if ( ! empty( $crm_test ) ) : ?>
					<p class="iam-crm-test <?php echo ! empty( $crm_test['ok'] ) ? 'is-ok' : 'is-ko'; ?>">
						<span class="dashicons dashicons-<?php echo ! empty( $crm_test['ok'] ) ? 'yes-alt' : 'warning'; ?>" aria-hidden="true"></span>
						<span>
							<strong><?php echo esc_html( $crm_test['message'] ?? '' ); ?></strong>
							<small>
								<?php
								printf(
									/* translators: 1 : CRM, 2 : date */
									esc_html__( 'Dernier test (%1$s) le %2$s', 'iam-leads' ),
									esc_html( $crm_test['fournisseur'] ?? '' ),
									esc_html( mysql2date( 'd/m/Y à H:i', $crm_test['date'] ?? '' ) )
								);
								?>
							</small>
						</span>
					</p>
				<?php endif; ?>

				<ul class="iam-crm-etats">
					<li><b><?php echo esc_html( number_format_i18n( $crm_etats['envoye'] ) ); ?></b> <?php esc_html_e( 'leads synchronisés', 'iam-leads' ); ?></li>
					<li><b><?php echo esc_html( number_format_i18n( $crm_etats['en_attente'] ) ); ?></b> <?php esc_html_e( 'en attente', 'iam-leads' ); ?></li>
					<li class="<?php echo $crm_etats['echec'] ? 'is-ko' : ''; ?>"><b><?php echo esc_html( number_format_i18n( $crm_etats['echec'] ) ); ?></b> <?php esc_html_e( 'en échec', 'iam-leads' ); ?></li>
				</ul>
			</div>
			<div class="iam-sec__foot">
				<a class="iam-adm-btn iam-adm-btn--sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_test_crm' ), 'iam_test_crm' ) ); ?>">
					<span class="dashicons dashicons-yes" aria-hidden="true"></span>
					<?php esc_html_e( 'Tester la connexion', 'iam-leads' ); ?>
				</a>
				<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--sm" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_sync_crm' ), 'iam_sync_crm' ) ); ?>">
					<span class="dashicons dashicons-update" aria-hidden="true"></span>
					<?php esc_html_e( 'Envoyer les leads existants', 'iam-leads' ); ?>
				</a>
				<span><?php esc_html_e( 'Enregistrez d\'abord. Le test ne crée aucun prospect ; l\'envoi des leads existants ne crée jamais de doublon.', 'iam-leads' ); ?></span>
			</div>
		</section>

		<!-- WhatsApp ------------------------------------------------------ -->
		<section class="iam-sec" id="iam-p-whatsapp" role="tabpanel" aria-labelledby="iam-p-whatsapp-tab">
			<?php
			$m::sec_tete(
				'whatsapp',
				__( 'Notifications WhatsApp', 'iam-leads' ),
				__( 'Prévient l\'équipe sur WhatsApp à chaque nouveau lead.', 'iam-leads' ),
				$m::interrupteur( 'whatsapp_active', (bool) $reglages['whatsapp_active'], __( 'Activé', 'iam-leads' ) )
			);
			?>
			<div class="iam-sec__body">
				<div class="iam-fields">
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_fournisseur"><?php esc_html_e( 'Fournisseur', 'iam-leads' ); ?></label>
						<select id="whatsapp_fournisseur" name="whatsapp_fournisseur">
							<?php foreach ( IAM_Leads_WhatsApp::fournisseurs() as $fournisseur ) : ?>
								<option value="<?php echo esc_attr( $fournisseur->id() ); ?>" <?php selected( $reglages['whatsapp_fournisseur'], $fournisseur->id() ); ?>>
									<?php echo esc_html( $fournisseur->nom() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_mode"><?php esc_html_e( 'Mode d\'envoi', 'iam-leads' ); ?></label>
						<select id="whatsapp_mode" name="whatsapp_mode">
							<option value="template" <?php selected( $reglages['whatsapp_mode'], 'template' ); ?>><?php esc_html_e( 'Template approuvé (recommandé)', 'iam-leads' ); ?></option>
							<option value="texte" <?php selected( $reglages['whatsapp_mode'], 'texte' ); ?>><?php esc_html_e( 'Texte libre (tests uniquement)', 'iam-leads' ); ?></option>
						</select>
						<p class="iam-field__hint"><?php esc_html_e( 'Le texte libre n\'est accepté que 24 h après un message du destinataire : sans template, l\'envoi échouera.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field iam-field--full">
						<label class="iam-field__label" for="whatsapp_destinataires"><?php esc_html_e( 'Numéros de l\'équipe', 'iam-leads' ); ?></label>
						<input type="text" id="whatsapp_destinataires" name="whatsapp_destinataires" value="<?php echo esc_attr( $reglages['whatsapp_destinataires'] ); ?>" placeholder="221338693636, 221776983966">
						<p class="iam-field__hint"><?php esc_html_e( 'Format international sans le +, séparés par des virgules.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_phone_id"><?php esc_html_e( 'Identifiant du numéro / Account SID', 'iam-leads' ); ?></label>
						<input type="text" id="whatsapp_phone_id" name="whatsapp_phone_id" value="<?php echo esc_attr( $reglages['whatsapp_phone_id'] ); ?>">
						<p class="iam-field__hint"><?php esc_html_e( 'Meta : Phone Number ID. Twilio : Account SID.', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_token"><?php esc_html_e( 'Jeton d\'accès', 'iam-leads' ); ?></label>
						<input type="password" id="whatsapp_token" name="whatsapp_token" autocomplete="new-password" value="<?php echo esc_attr( $reglages['whatsapp_token'] ); ?>">
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_template"><?php esc_html_e( 'Nom du template / ContentSid', 'iam-leads' ); ?></label>
						<input type="text" id="whatsapp_template" name="whatsapp_template" value="<?php echo esc_attr( $reglages['whatsapp_template'] ); ?>">
						<p class="iam-field__hint"><?php esc_html_e( 'Meta : nom exact du template. Twilio : ContentSid (HX…).', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field">
						<label class="iam-field__label" for="whatsapp_langue"><?php esc_html_e( 'Langue du template', 'iam-leads' ); ?></label>
						<input type="text" id="whatsapp_langue" name="whatsapp_langue" value="<?php echo esc_attr( $reglages['whatsapp_langue'] ); ?>" placeholder="fr">
						<p class="iam-field__hint"><?php esc_html_e( 'Code déclaré à Meta : fr, fr_FR, en_US…', 'iam-leads' ); ?></p>
					</div>
					<div class="iam-field iam-field--full">
						<label class="iam-field__label" for="whatsapp_expediteur"><?php esc_html_e( 'Numéro expéditeur (Twilio uniquement)', 'iam-leads' ); ?></label>
						<input type="text" id="whatsapp_expediteur" name="whatsapp_expediteur" value="<?php echo esc_attr( $reglages['whatsapp_expediteur'] ); ?>" placeholder="+221…">
					</div>
				</div>

				<details class="iam-vue-table">
					<summary><?php esc_html_e( 'Template à faire approuver par Meta', 'iam-leads' ); ?></summary>
					<p class="iam-field__hint"><?php esc_html_e( 'Catégorie « Utility », avec ce corps exact. Les sept variables sont remplies par le plugin, dans cet ordre. Le corps ne doit pas finir par une variable : Meta refuse ces templates.', 'iam-leads' ); ?></p>
					<textarea class="code" rows="9" readonly onclick="this.select();">🎓 Nouveau lead IAM
Nom : {{1}}
Programme : {{2}}
Téléphone : {{3}}
Pays : {{4}}
Source : {{5}}
Contact préféré : {{6}}
Reçu le : {{7}}
Consultez la fiche complète dans le tableau de bord IAM Leads.</textarea>
				</details>
			</div>
		</section>

		<!-- Journal ------------------------------------------------------- -->
		<section class="iam-sec" id="iam-p-journal" role="tabpanel" aria-labelledby="iam-p-journal-tab">
			<?php
			$m::sec_tete(
				'list-view',
				__( 'Journal des envois', 'iam-leads' ),
				__( 'Les 30 derniers événements : e-mails, CRM, WhatsApp, enregistrements.', 'iam-leads' ),
				$journal ? sprintf(
					'<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--sm" href="%1$s"><span class="dashicons dashicons-trash" aria-hidden="true"></span> %2$s</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_clear_log' ), 'iam_clear_log' ) ),
					esc_html__( 'Vider', 'iam-leads' )
				) : ''
			);
			?>
			<?php if ( ! $journal ) : ?>
				<div class="iam-sec__body"><p class="iam-field__hint"><?php esc_html_e( 'Aucun envoi enregistré pour le moment.', 'iam-leads' ); ?></p></div>
			<?php else : ?>
				<div class="iam-table-scroll">
					<table class="iam-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'iam-leads' ); ?></th>
								<th><?php esc_html_e( 'Canal', 'iam-leads' ); ?></th>
								<th><?php esc_html_e( 'Résultat', 'iam-leads' ); ?></th>
								<th><?php esc_html_e( 'Message', 'iam-leads' ); ?></th>
								<th><?php esc_html_e( 'Lead', 'iam-leads' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $journal as $ligne ) : ?>
							<tr>
								<td class="iam-col-date"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $ligne['date'] ) ); ?></td>
								<td><span class="iam-adm-pill iam-adm-pill--neutre"><?php echo esc_html( $ligne['canal'] ); ?></span></td>
								<td><span class="iam-badge iam-badge-<?php echo esc_attr( $ligne['niveau'] ); ?>"><?php echo esc_html( $ligne['niveau'] ); ?></span></td>
								<td><?php echo esc_html( wp_trim_words( $ligne['message'], 40 ) ); ?></td>
								<td>
									<?php if ( $ligne['lead_id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list&lead=' . $ligne['lead_id'] ) ); ?>">#<?php echo esc_html( $ligne['lead_id'] ); ?></a>
									<?php else : ?>
										<span class="iam-vide-inline">—</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>

		<div class="iam-wiz-foot">
			<p class="iam-wiz-foot__hint"><?php esc_html_e( 'Un seul bouton enregistre tous les onglets.', 'iam-leads' ); ?></p>
			<div class="iam-wiz-foot__right">
				<button type="submit" class="iam-adm-btn">
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e( 'Enregistrer les réglages', 'iam-leads' ); ?>
				</button>
			</div>
		</div>
	</form>
</div>
