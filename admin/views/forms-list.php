<?php
/**
 * Liste des formulaires : une carte par formulaire, comme le catalogue
 * de IAM Certifications.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$formulaires = IAM_Leads_Form_Builder::all();
$modes       = IAM_Leads_Form_Builder::modes_affichage();

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

$url_nouveau = admin_url( 'admin.php?page=iam-leads-forms&action=new' );
?>
<div class="wrap iam-wrap">
	<?php
	IAM_Leads_Admin_Menu::entete(
		__( 'Captation', 'iam-leads' ),
		__( 'Formulaires', 'iam-leads' ),
		sprintf(
			'<a href="%1$s" class="iam-adm-btn"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> %2$s</a>',
			esc_url( $url_nouveau ),
			esc_html__( 'Nouveau formulaire', 'iam-leads' )
		),
		esc_html__( 'Insérez un formulaire n’importe où avec son shortcode : cliquez dessus pour le copier.', 'iam-leads' )
	);
	?>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<div class="iam-adm-list">
		<?php if ( ! $formulaires ) : ?>
			<div class="iam-adm-empty">
				<span class="dashicons dashicons-feedback" aria-hidden="true"></span>
				<p><?php esc_html_e( 'Aucun formulaire pour le moment.', 'iam-leads' ); ?></p>
				<a class="iam-adm-btn" href="<?php echo esc_url( $url_nouveau ); ?>"><?php esc_html_e( 'Créer le premier formulaire', 'iam-leads' ); ?></a>
			</div>
		<?php endif; ?>

		<?php foreach ( $formulaires as $form ) : ?>
			<?php
			$total     = $totaux_par_form[ (int) $form->id ] ?? 0;
			$url_edit  = admin_url( 'admin.php?page=iam-leads-forms&action=edit&id=' . $form->id );
			$url_leads = admin_url( 'admin.php?page=iam-leads-list&form_id=' . $form->id );
			$url_suppr = wp_nonce_url( admin_url( 'admin-post.php?action=iam_delete_form&id=' . $form->id ), 'iam_delete_form' );
			$mode      = $form->reglages['mode_affichage'] ?? '';
			$etapes    = count(
				array_filter(
					(array) $form->champs,
					static function ( $champ ) {
						return 'etape' === ( $champ['type'] ?? '' );
					}
				)
			);
			$questions = count( $form->champs ) - $etapes;
			?>
			<article class="iam-adm-row">
				<a class="iam-adm-row__thumb" href="<?php echo esc_url( $url_edit ); ?>" aria-hidden="true" tabindex="-1">
					<span class="dashicons <?php echo 'etapes' === $mode ? 'dashicons-editor-ol' : 'dashicons-feedback'; ?>"></span>
				</a>
				<div class="iam-adm-row__main">
					<p class="iam-adm-row__meta">
						<span class="iam-adm-pill"><?php echo esc_html( $modes[ $mode ] ?? __( 'Formulaire', 'iam-leads' ) ); ?></span>
						<span>#<?php echo esc_html( $form->id ); ?></span>
						<?php if ( ! empty( $form->programme ) ) : ?>
							<span><?php echo esc_html( $form->programme ); ?></span>
						<?php endif; ?>
					</p>
					<h2 class="iam-adm-row__title"><a href="<?php echo esc_url( $url_edit ); ?>"><?php echo esc_html( $form->nom ); ?></a></h2>
					<p class="iam-adm-row__facts">
						<span>
							<?php
							/* translators: %d : nombre de champs */
							echo esc_html( sprintf( _n( '%d champ', '%d champs', $questions, 'iam-leads' ), $questions ) );
							if ( $etapes ) {
								/* translators: %d : nombre d'étapes */
								echo ' · ' . esc_html( sprintf( _n( '%d étape', '%d étapes', $etapes + 1, 'iam-leads' ), $etapes + 1 ) );
							}
							?>
						</span>
						<span title="<?php esc_attr_e( 'Destinataires des notifications', 'iam-leads' ); ?>">
							<span class="dashicons dashicons-email-alt" aria-hidden="true" style="font-size:14px;width:14px;height:14px;vertical-align:-2px;"></span>
							<?php
							$dest = IAM_Leads_Emails::destinataires( $form );
							echo esc_html( $dest ? implode( ', ', $dest ) : __( 'aucun destinataire', 'iam-leads' ) );
							if ( empty( $form->reglages['destinataires'] ) && $dest ) {
								echo ' <em>(' . esc_html__( 'par défaut', 'iam-leads' ) . ')</em>';
							}
							?>
						</span>
						<button type="button" class="iam-copiable" title="<?php esc_attr_e( 'Copier le shortcode', 'iam-leads' ); ?>">[iam_form id="<?php echo esc_attr( $form->id ); ?>"]</button>
					</p>
				</div>
				<a class="iam-adm-row__stat" href="<?php echo esc_url( $url_leads ); ?>" title="<?php esc_attr_e( 'Voir les leads de ce formulaire', 'iam-leads' ); ?>">
					<b><?php echo esc_html( number_format_i18n( $total ) ); ?></b>
					<small><?php echo esc_html( _n( 'lead', 'leads', $total, 'iam-leads' ) ); ?></small>
				</a>
				<div class="iam-adm-row__actions">
					<a class="iam-adm-btn iam-adm-btn--sm" href="<?php echo esc_url( $url_edit ); ?>"><?php esc_html_e( 'Modifier', 'iam-leads' ); ?></a>
					<a class="iam-adm-icon iam-adm-icon--danger" href="<?php echo esc_url( $url_suppr ); ?>" title="<?php esc_attr_e( 'Supprimer', 'iam-leads' ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce formulaire ? Les leads déjà reçus sont conservés.', 'iam-leads' ) ); ?>');">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Supprimer', 'iam-leads' ); ?></span>
					</a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</div>
