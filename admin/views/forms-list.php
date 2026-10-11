<?php
/**
 * Liste des formulaires.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$formulaires = IAM_Leads_Form_Builder::all();

/*
 * Nombre de leads par formulaire, en une requête.
 * Auparavant un COUNT(*) était exécuté dans la boucle d'affichage :
 * autant de requêtes que de formulaires.
 */
$totaux_par_form = array();

if ( $formulaires ) {
	global $wpdb;

	$lignes = (array) $wpdb->get_results( 'SELECT form_id, COUNT(*) AS total FROM ' . IAM_Leads_Lead_Manager::table() . ' GROUP BY form_id' ); // phpcs:ignore

	foreach ( $lignes as $ligne ) {
		$totaux_par_form[ (int) $ligne->form_id ] = (int) $ligne->total;
	}
}
?>
<div class="wrap iam-wrap">
	<?php
	IAM_Leads_Admin_Menu::entete(
		__( 'Captation', 'iam-leads' ),
		__( 'Formulaires', 'iam-leads' ),
		sprintf(
			'<a href="%1$s" class="button button-primary"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> %2$s</a>',
			esc_url( admin_url( 'admin.php?page=iam-leads-forms&action=new' ) ),
			esc_html__( 'Créer un formulaire', 'iam-leads' )
		),
		__( 'Insérez un formulaire n’importe où avec son shortcode.', 'iam-leads' )
	);
	?>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<?php if ( ! $formulaires ) : ?>
		<div class="iam-vide">
			<p><?php esc_html_e( 'Aucun formulaire pour le moment.', 'iam-leads' ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-forms&action=new' ) ); ?>">
				<?php esc_html_e( 'Créer le premier formulaire', 'iam-leads' ); ?>
			</a>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped iam-table">
			<thead>
				<tr>
					<th style="width:60px;"><?php esc_html_e( 'ID', 'iam-leads' ); ?></th>
					<th><?php esc_html_e( 'Nom', 'iam-leads' ); ?></th>
					<th><?php esc_html_e( 'Champs', 'iam-leads' ); ?></th>
					<th><?php esc_html_e( 'Shortcode', 'iam-leads' ); ?></th>
					<th><?php esc_html_e( 'Leads reçus', 'iam-leads' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'iam-leads' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $formulaires as $form ) : ?>
				<?php
				$total     = $totaux_par_form[ (int) $form->id ] ?? 0;
				$url_suppr = wp_nonce_url( admin_url( 'admin-post.php?action=iam_delete_form&id=' . $form->id ), 'iam_delete_form' );
				?>
				<tr>
					<td><?php echo esc_html( $form->id ); ?></td>
					<td>
						<strong>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-forms&action=edit&id=' . $form->id ) ); ?>">
								<?php echo esc_html( $form->nom ); ?>
							</a>
						</strong>
					</td>
					<td><?php echo esc_html( count( $form->champs ) ); ?></td>
					<td><code class="iam-copiable">[iam_form id="<?php echo esc_attr( $form->id ); ?>"]</code></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list&form_id=' . $form->id ) ); ?>">
							<?php echo esc_html( $total ); ?>
						</a>
					</td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-forms&action=edit&id=' . $form->id ) ); ?>">
							<?php esc_html_e( 'Modifier', 'iam-leads' ); ?>
						</a>
						<a class="button button-small iam-danger" href="<?php echo esc_url( $url_suppr ); ?>"
							onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce formulaire ? Les leads déjà reçus sont conservés.', 'iam-leads' ) ); ?>');">
							<?php esc_html_e( 'Supprimer', 'iam-leads' ); ?>
						</a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
