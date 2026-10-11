<?php
/**
 * Réglages du plugin.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$reglages   = IAM_Leads::get_settings();
$plaquettes = (array) $reglages['plaquettes'];
$journal    = IAM_Leads_Logger::lire( 30 );
?>
<div class="wrap iam-wrap">
	<?php IAM_Leads_Admin_Menu::entete( __( 'Configuration', 'iam-leads' ), __( 'Réglages', 'iam-leads' ), '', __( 'Notifications, envoi des e-mails, plaquettes, CRM et WhatsApp.', 'iam-leads' ) ); ?>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="iam_save_settings">
		<?php wp_nonce_field( 'iam_save_settings' ); ?>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Notifications par e-mail', 'iam-leads' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="admin_emails"><?php esc_html_e( 'Destinataires internes', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="admin_emails" name="admin_emails" class="regular-text"
							value="<?php echo esc_attr( $reglages['admin_emails'] ); ?>">
						<p class="description"><?php esc_html_e( 'Séparez plusieurs adresses par des virgules. Ex. : info@groupeiam.com, registrariat@groupeiam.com', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Accusé de réception', 'iam-leads' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="email_confirmation" value="1" <?php checked( $reglages['email_confirmation'], 1 ); ?>>
							<?php esc_html_e( 'Envoyer une confirmation au prospect', 'iam-leads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="email_expediteur_nom"><?php esc_html_e( 'Nom de l\'expéditeur', 'iam-leads' ); ?></label></th>
					<td><input type="text" id="email_expediteur_nom" name="email_expediteur_nom" class="regular-text"
						value="<?php echo esc_attr( $reglages['email_expediteur_nom'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="email_expediteur_mail"><?php esc_html_e( 'Adresse d\'expédition', 'iam-leads' ); ?></label></th>
					<td>
						<input type="email" id="email_expediteur_mail" name="email_expediteur_mail" class="regular-text"
							value="<?php echo esc_attr( $reglages['email_expediteur_mail'] ); ?>">
						<p class="description"><?php esc_html_e( 'Utilisez une adresse du domaine géré chez Systalink pour éviter le classement en indésirable.', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="privacy_url"><?php esc_html_e( 'Politique de confidentialité', 'iam-leads' ); ?></label></th>
					<td>
						<input type="url" id="privacy_url" name="privacy_url" class="regular-text"
							value="<?php echo esc_attr( $reglages['privacy_url'] ); ?>">
						<p class="description"><?php esc_html_e( 'Le lien s\'affiche à côté de la case de consentement.', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="delai_anti_spam"><?php esc_html_e( 'Délai anti-robot', 'iam-leads' ); ?></label></th>
					<td>
						<input type="number" id="delai_anti_spam" name="delai_anti_spam" min="0" max="30" class="small-text"
							value="<?php echo esc_attr( $reglages['delai_anti_spam'] ); ?>">
						<?php esc_html_e( 'secondes minimum entre l\'affichage et l\'envoi', 'iam-leads' ); ?>
					</td>
				</tr>
			</table>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Serveur d\'envoi (SMTP)', 'iam-leads' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Un conteneur Docker n\'embarque aucun service d\'envoi : sans SMTP, WordPress ne peut expédier aucun e-mail, ni notification interne ni accusé de réception. Renseignez ici le compte fourni par votre hébergeur de messagerie.', 'iam-leads' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activation', 'iam-leads' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="smtp_active" value="1" <?php checked( $reglages['smtp_active'], 1 ); ?>>
							<?php esc_html_e( 'Envoyer les e-mails via un serveur SMTP', 'iam-leads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smtp_hote"><?php esc_html_e( 'Serveur', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="smtp_hote" name="smtp_hote" class="regular-text"
							value="<?php echo esc_attr( $reglages['smtp_hote'] ); ?>" placeholder="mail.groupeiam.com">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smtp_port"><?php esc_html_e( 'Port', 'iam-leads' ); ?></label></th>
					<td>
						<input type="number" id="smtp_port" name="smtp_port" min="1" max="65535" class="small-text"
							value="<?php echo esc_attr( $reglages['smtp_port'] ); ?>">
						<select name="smtp_securite">
							<option value="tls" <?php selected( $reglages['smtp_securite'], 'tls' ); ?>><?php esc_html_e( 'STARTTLS (port 587)', 'iam-leads' ); ?></option>
							<option value="ssl" <?php selected( $reglages['smtp_securite'], 'ssl' ); ?>><?php esc_html_e( 'SSL/TLS (port 465)', 'iam-leads' ); ?></option>
							<option value="aucune" <?php selected( $reglages['smtp_securite'], 'aucune' ); ?>><?php esc_html_e( 'Aucun chiffrement (port 25)', 'iam-leads' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smtp_utilisateur"><?php esc_html_e( 'Identifiant', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="smtp_utilisateur" name="smtp_utilisateur" class="regular-text"
							value="<?php echo esc_attr( $reglages['smtp_utilisateur'] ); ?>" autocomplete="off">
						<p class="description"><?php esc_html_e( 'Le plus souvent l\'adresse complète, par ex. info@groupeiam.com. Laissez vide pour un envoi sans authentification.', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="smtp_motdepasse"><?php esc_html_e( 'Mot de passe', 'iam-leads' ); ?></label></th>
					<td>
						<?php if ( defined( 'IAM_LEADS_SMTP_PASS' ) && IAM_LEADS_SMTP_PASS ) : ?>
							<p class="description">
								<strong><?php esc_html_e( 'Défini dans wp-config.php', 'iam-leads' ); ?></strong> —
								<?php esc_html_e( 'la constante IAM_LEADS_SMTP_PASS prime sur ce champ.', 'iam-leads' ); ?>
							</p>
						<?php else : ?>
							<input type="password" id="smtp_motdepasse" name="smtp_motdepasse" class="regular-text"
								value="" autocomplete="new-password"
								placeholder="<?php echo $reglages['smtp_motdepasse'] ? esc_attr__( '•••••••• (enregistré)', 'iam-leads' ) : ''; ?>">
							<p class="description">
								<?php esc_html_e( 'Laissez vide pour conserver le mot de passe actuel. Pour le garder hors de la base, définissez plutôt IAM_LEADS_SMTP_PASS dans wp-config.php.', 'iam-leads' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p>
				<a class="button button-secondary"
					href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_test_email' ), 'iam_test_email' ) ); ?>">
					<?php esc_html_e( 'Envoyer un e-mail de test', 'iam-leads' ); ?>
				</a>
				<span class="description">
					<?php esc_html_e( 'Enregistrez d\'abord les réglages. Le test part vers les destinataires internes ; le résultat détaillé s\'inscrit dans le journal, en bas de cette page.', 'iam-leads' ); ?>
				</span>
			</p>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Plaquettes par programme', 'iam-leads' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Le lien de téléchargement proposé après l\'envoi dépend du programme choisi par le visiteur. Sans plaquette, le message « Notre équipe vous contactera sous 48h » s\'affiche.', 'iam-leads' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<?php foreach ( IAM_Leads_Form_Builder::programmes() as $programme ) : ?>
					<?php
					$piece_id = absint( $plaquettes[ $programme ] ?? 0 );
					$url      = $piece_id ? wp_get_attachment_url( $piece_id ) : '';
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $programme ); ?></th>
						<td class="iam-plaquette-ligne">
							<input type="hidden" class="iam-plaquette-id" name="plaquettes[<?php echo esc_attr( $programme ); ?>]"
								value="<?php echo esc_attr( $piece_id ); ?>">
							<span class="iam-fichier iam-plaquette-nom">
								<?php echo $url ? esc_html( basename( $url ) ) : esc_html__( 'Aucun fichier', 'iam-leads' ); ?>
							</span>
							<button type="button" class="button iam-plaquette-choisir"><?php esc_html_e( 'Choisir un PDF', 'iam-leads' ); ?></button>
							<button type="button" class="button iam-plaquette-retirer"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Intégration CRM', 'iam-leads' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activer', 'iam-leads' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="crm_active" value="1" <?php checked( $reglages['crm_active'], 1 ); ?>>
							<?php esc_html_e( 'Envoyer chaque lead vers le CRM', 'iam-leads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="crm_fournisseur"><?php esc_html_e( 'Fournisseur', 'iam-leads' ); ?></label></th>
					<td>
						<select id="crm_fournisseur" name="crm_fournisseur">
							<?php foreach ( IAM_Leads_CRM_Integration::fournisseurs() as $fournisseur ) : ?>
								<option value="<?php echo esc_attr( $fournisseur->id() ); ?>" <?php selected( $reglages['crm_fournisseur'], $fournisseur->id() ); ?>>
									<?php echo esc_html( $fournisseur->nom() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="hubspot_token"><?php esc_html_e( 'Jeton HubSpot', 'iam-leads' ); ?></label></th>
					<td>
						<input type="password" id="hubspot_token" name="hubspot_token" class="regular-text" autocomplete="new-password"
							value="<?php echo esc_attr( $reglages['hubspot_token'] ); ?>">
						<p class="description"><?php esc_html_e( 'Jeton d\'application privée (Private App Token) avec le droit crm.objects.contacts.write.', 'iam-leads' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Notifications WhatsApp', 'iam-leads' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Activer', 'iam-leads' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="whatsapp_active" value="1" <?php checked( $reglages['whatsapp_active'], 1 ); ?>>
							<?php esc_html_e( 'Prévenir l\'équipe à chaque nouveau lead', 'iam-leads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_fournisseur"><?php esc_html_e( 'Fournisseur', 'iam-leads' ); ?></label></th>
					<td>
						<select id="whatsapp_fournisseur" name="whatsapp_fournisseur">
							<?php foreach ( IAM_Leads_WhatsApp::fournisseurs() as $fournisseur ) : ?>
								<option value="<?php echo esc_attr( $fournisseur->id() ); ?>" <?php selected( $reglages['whatsapp_fournisseur'], $fournisseur->id() ); ?>>
									<?php echo esc_html( $fournisseur->nom() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_mode"><?php esc_html_e( 'Mode d\'envoi', 'iam-leads' ); ?></label></th>
					<td>
						<select id="whatsapp_mode" name="whatsapp_mode">
							<option value="template" <?php selected( $reglages['whatsapp_mode'], 'template' ); ?>>
								<?php esc_html_e( 'Template approuvé (recommandé)', 'iam-leads' ); ?>
							</option>
							<option value="texte" <?php selected( $reglages['whatsapp_mode'], 'texte' ); ?>>
								<?php esc_html_e( 'Texte libre (tests uniquement)', 'iam-leads' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'WhatsApp n\'accepte un texte libre que dans les 24 h suivant un message reçu du destinataire. Une notification d\'équipe tombe presque toujours hors de cette fenêtre : sans template approuvé, l\'envoi échouera.', 'iam-leads' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_destinataires"><?php esc_html_e( 'Numéros de l\'équipe', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="whatsapp_destinataires" name="whatsapp_destinataires" class="regular-text"
							value="<?php echo esc_attr( $reglages['whatsapp_destinataires'] ); ?>">
						<p class="description"><?php esc_html_e( 'Format international sans le +, séparés par des virgules. Ex. : 221338693636, 221776983966', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_phone_id"><?php esc_html_e( 'Identifiant du numéro / Account SID', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="whatsapp_phone_id" name="whatsapp_phone_id" class="regular-text"
							value="<?php echo esc_attr( $reglages['whatsapp_phone_id'] ); ?>">
						<p class="description"><?php esc_html_e( 'Meta : le Phone Number ID de l\'expéditeur. Twilio : l\'Account SID.', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_token"><?php esc_html_e( 'Jeton d\'accès', 'iam-leads' ); ?></label></th>
					<td><input type="password" id="whatsapp_token" name="whatsapp_token" class="regular-text" autocomplete="new-password"
						value="<?php echo esc_attr( $reglages['whatsapp_token'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_template"><?php esc_html_e( 'Nom du template / ContentSid', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="whatsapp_template" name="whatsapp_template" class="regular-text"
							value="<?php echo esc_attr( $reglages['whatsapp_template'] ); ?>">
						<p class="description"><?php esc_html_e( 'Meta : le nom exact du template approuvé. Twilio : le ContentSid (commence par HX).', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_langue"><?php esc_html_e( 'Langue du template', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="whatsapp_langue" name="whatsapp_langue" class="small-text"
							value="<?php echo esc_attr( $reglages['whatsapp_langue'] ); ?>">
						<p class="description"><?php esc_html_e( 'Code de langue déclaré à Meta lors de la création du template : fr, fr_FR, en_US…', 'iam-leads' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whatsapp_expediteur"><?php esc_html_e( 'Numéro expéditeur (Twilio)', 'iam-leads' ); ?></label></th>
					<td>
						<input type="text" id="whatsapp_expediteur" name="whatsapp_expediteur" class="regular-text"
							value="<?php echo esc_attr( $reglages['whatsapp_expediteur'] ); ?>">
						<p class="description"><?php esc_html_e( 'À renseigner uniquement avec Twilio. Format international avec le +.', 'iam-leads' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Template à faire approuver', 'iam-leads' ); ?></h3>
			<p class="description">
				<?php esc_html_e( 'Créez un template de catégorie « Utility » dans le WhatsApp Manager de Meta, avec ce corps exact. Les sept variables sont remplies automatiquement par le plugin, dans cet ordre.', 'iam-leads' ); ?>
			</p>
			<textarea class="large-text code" rows="10" readonly onclick="this.select();">🎓 Nouveau lead IAM
Nom : {{1}}
Programme : {{2}}
Téléphone : {{3}}
Pays : {{4}}
Source : {{5}}
Contact préféré : {{6}}
Reçu le : {{7}}
Consultez la fiche complète dans le tableau de bord IAM Leads.</textarea>
			<p class="description">
				<?php esc_html_e( 'Ne terminez pas le corps par une variable : Meta refuse ces templates. La dernière ligne est là pour cette raison.', 'iam-leads' ); ?>
			</p>
		</div>

		<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Enregistrer les réglages', 'iam-leads' ); ?></button></p>
	</form>

	<div class="iam-carte">
		<h2><?php esc_html_e( 'Journal des envois', 'iam-leads' ); ?></h2>

		<?php if ( ! $journal ) : ?>
			<p><?php esc_html_e( 'Aucun envoi enregistré pour le moment.', 'iam-leads' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped iam-table">
				<thead>
					<tr>
						<th style="width:150px;"><?php esc_html_e( 'Date', 'iam-leads' ); ?></th>
						<th style="width:100px;"><?php esc_html_e( 'Canal', 'iam-leads' ); ?></th>
						<th style="width:90px;"><?php esc_html_e( 'Résultat', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Message', 'iam-leads' ); ?></th>
						<th style="width:80px;"><?php esc_html_e( 'Lead', 'iam-leads' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $journal as $ligne ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $ligne['date'] ) ); ?></td>
						<td><?php echo esc_html( $ligne['canal'] ); ?></td>
						<td><span class="iam-badge iam-badge-<?php echo esc_attr( $ligne['niveau'] ); ?>"><?php echo esc_html( $ligne['niveau'] ); ?></span></td>
						<td><?php echo esc_html( wp_trim_words( $ligne['message'], 40 ) ); ?></td>
						<td>
							<?php if ( $ligne['lead_id'] ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list&lead=' . $ligne['lead_id'] ) ); ?>">
									#<?php echo esc_html( $ligne['lead_id'] ); ?>
								</a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=iam_clear_log' ), 'iam_clear_log' ) ); ?>">
					<?php esc_html_e( 'Vider le journal', 'iam-leads' ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>
</div>
