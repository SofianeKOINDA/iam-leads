<?php
/**
 * Liste des leads.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$manager = IAM_Leads::instance()->leads;
$statuts = IAM_Leads::statuts();

$filtres = array(
	'form_id'   => absint( $_GET['form_id'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification
	'programme' => sanitize_text_field( wp_unslash( $_GET['programme'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'statut'    => sanitize_key( $_GET['statut'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification
	'source'    => sanitize_text_field( wp_unslash( $_GET['source'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'recherche' => sanitize_text_field( wp_unslash( $_GET['recherche'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'du'        => sanitize_text_field( wp_unslash( $_GET['du'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'au'        => sanitize_text_field( wp_unslash( $_GET['au'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'page'      => max( 1, absint( $_GET['paged'] ?? 1 ) ), // phpcs:ignore WordPress.Security.NonceVerification
	'par_page'  => 25,
);

$resultat = $manager->query( $filtres );
$pages    = (int) ceil( $resultat['total'] / $filtres['par_page'] );

// Seuls les filtres de recherche sont transmis à l'export : la pagination de
// la liste ne doit pas limiter le fichier produit.
$filtres_export = array_filter(
	array(
		'form_id'   => $filtres['form_id'],
		'programme' => $filtres['programme'],
		'statut'    => $filtres['statut'],
		'source'    => $filtres['source'],
		'recherche' => $filtres['recherche'],
		'du'        => $filtres['du'],
		'au'        => $filtres['au'],
	),
	static function ( $valeur ) {
		return '' !== (string) $valeur && '0' !== (string) $valeur;
	}
);

$export_groupes = IAM_Leads_Export::groupes();

// Le registre porte l'ordre des colonnes du fichier, l'affichage les regroupe.
// Une colonne ajoutée par un filtre avec un groupe inconnu atterrit dans
// « Technique » plutôt que de disparaître de l'interface.
$export_par_groupe = array_fill_keys( array_keys( $export_groupes ), array() );
foreach ( IAM_Leads_Export::colonnes() as $cle => $colonne ) {
	$groupe = isset( $export_par_groupe[ $colonne['groupe'] ] ) ? $colonne['groupe'] : 'technique';

	$export_par_groupe[ $groupe ][ $cle ] = $colonne;
}

$export_detail   = IAM_Leads_Export::colonnes_detail( $filtres['form_id'] );
$export_choisies = IAM_Leads_Export::preference_colonnes();
$export_format   = IAM_Leads_Export::preference_format();
$export_detaille = IAM_Leads_Export::preference_detail();
$export_xlsx     = IAM_Leads_XLSX_Writer::disponible();
?>
<div class="wrap iam-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Leads', 'iam-leads' ); ?></h1>
	<button type="button" class="page-title-action" id="iam-export-bouton"
		aria-expanded="false" aria-controls="iam-export-panneau"><?php esc_html_e( 'Exporter', 'iam-leads' ); ?></button>
	<hr class="wp-header-end">

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		class="iam-carte iam-export" id="iam-export-panneau"
		data-defaut="<?php echo esc_attr( implode( ',', IAM_Leads_Export::defaut() ) ); ?>" hidden>

		<input type="hidden" name="action" value="iam_leads_export">
		<?php wp_nonce_field( 'iam_leads_export' ); ?>
		<?php foreach ( $filtres_export as $cle => $valeur ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $cle ); ?>" value="<?php echo esc_attr( $valeur ); ?>">
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Colonnes à exporter', 'iam-leads' ); ?></h2>

		<p class="iam-export-raccourcis">
			<button type="button" class="button button-small" data-selection="tout"><?php esc_html_e( 'Tout', 'iam-leads' ); ?></button>
			<button type="button" class="button button-small" data-selection="rien"><?php esc_html_e( 'Rien', 'iam-leads' ); ?></button>
			<button type="button" class="button button-small" data-selection="defaut"><?php esc_html_e( 'Défaut', 'iam-leads' ); ?></button>
		</p>

		<div class="iam-export-groupes">
			<?php foreach ( $export_groupes as $groupe => $titre ) : ?>
				<fieldset class="iam-export-groupe">
					<legend><?php echo esc_html( $titre ); ?></legend>
					<?php foreach ( $export_par_groupe[ $groupe ] as $cle => $colonne ) : ?>
						<label>
							<input type="checkbox" name="colonnes[]" value="<?php echo esc_attr( $cle ); ?>"
								<?php checked( in_array( $cle, $export_choisies, true ) ); ?>>
							<?php echo esc_html( $colonne['label'] ); ?>
						</label>
						<?php if ( ! empty( $colonne['note'] ) ) : ?>
							<span class="iam-export-note"><?php echo esc_html( $colonne['note'] ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				</fieldset>
			<?php endforeach; ?>

			<?php if ( $export_detail ) : ?>
				<fieldset class="iam-export-groupe iam-export-detail">
					<legend><?php esc_html_e( 'Réponses du formulaire', 'iam-leads' ); ?></legend>
					<label class="iam-export-bascule">
						<input type="checkbox" name="detail" value="1" id="iam-export-detail" <?php checked( $export_detaille ); ?>>
						<strong><?php esc_html_e( 'Détailler les réponses', 'iam-leads' ); ?></strong>
					</label>
					<span class="iam-export-note">
						<?php esc_html_e( 'Une colonne par question sans correspondance, à la place de « Réponses complètes ».', 'iam-leads' ); ?>
					</span>
					<?php foreach ( $export_detail as $cle => $colonne ) : ?>
						<label>
							<input type="checkbox" name="colonnes[]" value="<?php echo esc_attr( $cle ); ?>" checked>
							<?php echo esc_html( $colonne['label'] ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		</div>

		<?php if ( ! $filtres['form_id'] ) : ?>
			<p class="iam-export-note iam-export-astuce">
				<?php esc_html_e( 'Filtrez sur un formulaire pour pouvoir détailler ses réponses en colonnes séparées.', 'iam-leads' ); ?>
			</p>
		<?php endif; ?>

		<div class="iam-export-pied">
			<p class="iam-export-formats">
				<strong><?php esc_html_e( 'Format', 'iam-leads' ); ?></strong>
				<label>
					<input type="radio" name="format" value="csv" <?php checked( 'csv' === $export_format || ! $export_xlsx ); ?>>
					<?php esc_html_e( 'CSV', 'iam-leads' ); ?>
				</label>
				<?php if ( $export_xlsx ) : ?>
					<label>
						<input type="radio" name="format" value="xlsx" <?php checked( 'xlsx', $export_format ); ?>>
						<?php esc_html_e( 'Excel (XLSX)', 'iam-leads' ); ?>
					</label>
				<?php endif; ?>
			</p>

			<p class="iam-export-lancer">
				<span class="iam-export-note">
					<?php
					printf(
						/* translators: %s : nombre de leads */
						esc_html( _n( '%s lead sera exporté', '%s leads seront exportés', $resultat['total'], 'iam-leads' ) ),
						esc_html( number_format_i18n( $resultat['total'] ) )
					);
					?>
				</span>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Exporter', 'iam-leads' ); ?></button>
			</p>
		</div>
	</form>

	<form method="get" class="iam-filtres">
		<input type="hidden" name="page" value="iam-leads-list">

		<select name="form_id">
			<option value=""><?php esc_html_e( 'Tous les formulaires', 'iam-leads' ); ?></option>
			<?php foreach ( IAM_Leads_Form_Builder::all() as $f ) : ?>
				<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $filtres['form_id'], $f->id ); ?>>
					<?php echo esc_html( $f->nom ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="programme">
			<option value=""><?php esc_html_e( 'Tous les programmes', 'iam-leads' ); ?></option>
			<?php foreach ( $manager->valeurs_distinctes( 'programme' ) as $valeur ) : ?>
				<option value="<?php echo esc_attr( $valeur ); ?>" <?php selected( $filtres['programme'], $valeur ); ?>>
					<?php echo esc_html( $valeur ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="statut">
			<option value=""><?php esc_html_e( 'Tous les statuts', 'iam-leads' ); ?></option>
			<?php foreach ( $statuts as $cle => $libelle ) : ?>
				<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $filtres['statut'], $cle ); ?>>
					<?php echo esc_html( $libelle ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="source">
			<option value=""><?php esc_html_e( 'Toutes les sources', 'iam-leads' ); ?></option>
			<?php foreach ( $manager->valeurs_distinctes( 'source' ) as $valeur ) : ?>
				<option value="<?php echo esc_attr( $valeur ); ?>" <?php selected( $filtres['source'], $valeur ); ?>>
					<?php echo esc_html( $valeur ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="iam-filtre-date">
			<?php esc_html_e( 'Du', 'iam-leads' ); ?>
			<input type="date" name="du" value="<?php echo esc_attr( $filtres['du'] ); ?>">
		</label>
		<label class="iam-filtre-date">
			<?php esc_html_e( 'au', 'iam-leads' ); ?>
			<input type="date" name="au" value="<?php echo esc_attr( $filtres['au'] ); ?>">
		</label>

		<input type="search" name="recherche" value="<?php echo esc_attr( $filtres['recherche'] ); ?>"
			placeholder="<?php esc_attr_e( 'Nom, e-mail, téléphone…', 'iam-leads' ); ?>">

		<button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'iam-leads' ); ?></button>
		<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list' ) ); ?>">
			<?php esc_html_e( 'Réinitialiser', 'iam-leads' ); ?>
		</a>
	</form>

	<p class="iam-total">
		<?php
		printf(
			/* translators: %s : nombre de leads */
			esc_html( _n( '%s lead', '%s leads', $resultat['total'], 'iam-leads' ) ),
			esc_html( number_format_i18n( $resultat['total'] ) )
		);
		?>
	</p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width:140px;"><?php esc_html_e( 'Date', 'iam-leads' ); ?></th>
				<th><?php esc_html_e( 'Nom', 'iam-leads' ); ?></th>
				<th><?php esc_html_e( 'Programme', 'iam-leads' ); ?></th>
				<th><?php esc_html_e( 'Source', 'iam-leads' ); ?></th>
				<th style="width:120px;"><?php esc_html_e( 'Statut', 'iam-leads' ); ?></th>
				<th style="width:140px;"><?php esc_html_e( 'Actions', 'iam-leads' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( ! $resultat['items'] ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'Aucun lead ne correspond à ces filtres.', 'iam-leads' ); ?></td></tr>
		<?php endif; ?>

		<?php foreach ( $resultat['items'] as $lead ) : ?>
			<?php $url_detail = admin_url( 'admin.php?page=iam-leads-list&lead=' . $lead->id ); ?>
			<tr>
				<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $lead->date_creation ) ); ?></td>
				<td>
					<strong><a href="<?php echo esc_url( $url_detail ); ?>"><?php echo esc_html( $lead->nom_complet ? $lead->nom_complet : __( '(sans nom)', 'iam-leads' ) ); ?></a></strong>
					<div class="iam-sous-ligne"><?php echo esc_html( $lead->email ); ?></div>
				</td>
				<td><?php echo esc_html( $lead->programme ? $lead->programme : '—' ); ?></td>
				<td>
					<?php echo esc_html( $lead->source ? $lead->source : '—' ); ?>
					<?php if ( $lead->utm_source ) : ?>
						<div class="iam-sous-ligne">utm : <?php echo esc_html( $lead->utm_source ); ?></div>
					<?php endif; ?>
				</td>
				<td><span class="iam-badge iam-badge-<?php echo esc_attr( $lead->statut ); ?>"><?php echo esc_html( $statuts[ $lead->statut ] ?? $lead->statut ); ?></span></td>
				<td>
					<a class="button button-small" href="<?php echo esc_url( $url_detail ); ?>"><?php esc_html_e( 'Ouvrir', 'iam-leads' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( $pages > 1 ) : ?>
		<div class="tablenav"><div class="tablenav-pages">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'current'   => $filtres['page'],
						'total'     => $pages,
						'prev_text' => '‹',
						'next_text' => '›',
					)
				)
			);
			?>
		</div></div>
	<?php endif; ?>
</div>
