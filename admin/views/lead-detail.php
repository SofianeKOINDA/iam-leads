<?php
/**
 * Fiche détaillée d'un lead.
 *
 * @package IAM_Leads
 *
 * @var object $lead
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$statuts   = IAM_Leads::statuts();
$form      = IAM_Leads_Form_Builder::get( $lead->form_id );
$url_suppr = wp_nonce_url( admin_url( 'admin-post.php?action=iam_delete_lead&id=' . $lead->id ), 'iam_delete_lead' );
?>
<div class="wrap iam-wrap">
	<?php
	IAM_Leads_Admin_Menu::entete(
		__( 'Lead', 'iam-leads' ),
		$lead->nom_complet ? $lead->nom_complet : __( 'Lead sans nom', 'iam-leads' ),
		sprintf(
			'<a href="%1$s" class="button"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> %2$s</a>',
			esc_url( admin_url( 'admin.php?page=iam-leads-list' ) ),
			esc_html__( 'Retour à la liste', 'iam-leads' )
		)
	);
	?>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<div class="iam-colonnes">
		<div class="iam-colonne-principale">

			<div class="iam-carte">
				<h2><?php esc_html_e( 'Réponses au formulaire', 'iam-leads' ); ?></h2>
				<table class="widefat striped iam-table-detail">
					<tbody>
					<?php foreach ( $lead->donnees_decodees as $champ ) : ?>
						<tr>
							<th style="width:38%;"><?php echo esc_html( $champ['label'] ); ?></th>
							<td>
								<?php
								$valeur = $champ['valeur'];
								if ( '' === $valeur ) {
									echo '<span class="iam-vide-inline">—</span>';
								} elseif ( is_email( $valeur ) ) {
									printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $valeur ) );
								} else {
									echo esc_html( $valeur );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="iam-carte">
				<h2><?php esc_html_e( 'Provenance', 'iam-leads' ); ?></h2>
				<table class="widefat striped iam-table-detail">
					<tbody>
						<tr><th style="width:38%;">utm_source</th><td><?php echo esc_html( $lead->utm_source ? $lead->utm_source : '—' ); ?></td></tr>
						<tr><th>utm_medium</th><td><?php echo esc_html( $lead->utm_medium ? $lead->utm_medium : '—' ); ?></td></tr>
						<tr><th>utm_campaign</th><td><?php echo esc_html( $lead->utm_campaign ? $lead->utm_campaign : '—' ); ?></td></tr>
						<tr><th>utm_content</th><td><?php echo esc_html( $lead->utm_content ? $lead->utm_content : '—' ); ?></td></tr>
						<tr><th>utm_term</th><td><?php echo esc_html( $lead->utm_term ? $lead->utm_term : '—' ); ?></td></tr>
						<tr>
							<th><?php esc_html_e( 'Page d\'origine', 'iam-leads' ); ?></th>
							<td><?php echo $lead->page_origine ? '<a href="' . esc_url( $lead->page_origine ) . '" target="_blank" rel="noopener">' . esc_html( $lead->page_origine ) . '</a>' : '—'; ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Page du formulaire', 'iam-leads' ); ?></th>
							<td><?php echo $lead->page_soumission ? '<a href="' . esc_url( $lead->page_soumission ) . '" target="_blank" rel="noopener">' . esc_html( $lead->page_soumission ) . '</a>' : '—'; ?></td>
						</tr>
						<tr><th><?php esc_html_e( 'Adresse IP', 'iam-leads' ); ?></th><td><?php echo esc_html( $lead->ip ? $lead->ip : '—' ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Navigateur', 'iam-leads' ); ?></th><td><?php echo esc_html( $lead->user_agent ? $lead->user_agent : '—' ); ?></td></tr>
					</tbody>
				</table>
			</div>

		</div>

		<div class="iam-colonne-laterale">

			<div class="iam-carte">
				<h2><?php esc_html_e( 'Suivi', 'iam-leads' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="iam_update_lead">
					<input type="hidden" name="id" value="<?php echo esc_attr( $lead->id ); ?>">
					<?php wp_nonce_field( 'iam_update_lead' ); ?>

					<p>
						<label for="iam-statut"><strong><?php esc_html_e( 'Statut du lead', 'iam-leads' ); ?></strong></label>
						<select id="iam-statut" name="statut" class="widefat">
							<?php foreach ( $statuts as $cle => $libelle ) : ?>
								<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $lead->statut, $cle ); ?>>
									<?php echo esc_html( $libelle ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</p>

					<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer le statut', 'iam-leads' ); ?></button></p>
				</form>

				<hr>

				<p><strong><?php esc_html_e( 'Reçu le', 'iam-leads' ); ?></strong><br>
					<?php echo esc_html( mysql2date( 'd/m/Y à H:i', $lead->date_creation ) ); ?></p>

				<p><strong><?php esc_html_e( 'Formulaire', 'iam-leads' ); ?></strong><br>
					<?php echo esc_html( $form ? $form->nom : __( 'formulaire supprimé', 'iam-leads' ) ); ?></p>

				<p><strong><?php esc_html_e( 'Envoi CRM', 'iam-leads' ); ?></strong><br>
					<span class="iam-badge iam-badge-<?php echo esc_attr( $lead->crm_status ); ?>"><?php echo esc_html( $lead->crm_status ); ?></span></p>

				<p><strong><?php esc_html_e( 'Notification WhatsApp', 'iam-leads' ); ?></strong><br>
					<span class="iam-badge iam-badge-<?php echo esc_attr( $lead->whatsapp_status ); ?>"><?php echo esc_html( $lead->whatsapp_status ); ?></span></p>

				<?php if ( $lead->telephone ) : ?>
					<p>
						<a class="button button-secondary" target="_blank" rel="noopener"
							href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $lead->telephone ) ); ?>">
							<?php esc_html_e( 'Ouvrir la conversation WhatsApp', 'iam-leads' ); ?>
						</a>
					</p>
				<?php endif; ?>

				<hr>

				<p>
					<a class="button iam-danger" href="<?php echo esc_url( $url_suppr ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce lead définitivement ?', 'iam-leads' ) ); ?>');">
						<?php esc_html_e( 'Supprimer ce lead', 'iam-leads' ); ?>
					</a>
				</p>
			</div>

		</div>
	</div>
</div>
