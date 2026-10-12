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
$m       = 'IAM_Leads_Admin_Menu';

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

/*
 * Onglets de statut : chaque compteur respecte les autres filtres actifs,
 * pour que « Contacté 3 » corresponde bien à ce que l'onglet affichera.
 */
$sans_statut = $filtres_export;
unset( $sans_statut['statut'] );

$compteurs = array( '' => $manager->query( array_merge( $sans_statut, array( 'par_page' => 1 ) ) )['total'] );
foreach ( array_keys( $statuts ) as $cle ) {
	$compteurs[ $cle ] = $manager->query( array_merge( $sans_statut, array( 'statut' => $cle, 'par_page' => 1 ) ) )['total'];
}

$url_liste    = admin_url( 'admin.php?page=iam-leads-list' );
$filtres_plus = (bool) ( $filtres['form_id'] || $filtres['programme'] || $filtres['source'] || $filtres['du'] || $filtres['au'] || $filtres['recherche'] );

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
	<?php
	$m::entete(
		__( 'Suivi commercial', 'iam-leads' ),
		__( 'Leads', 'iam-leads' ),
		sprintf(
			'<button type="button" class="iam-adm-btn iam-adm-btn--ghost" id="iam-export-bouton" aria-expanded="false" aria-controls="iam-export-panneau"><span class="dashicons dashicons-download" aria-hidden="true"></span> %s</button>',
			esc_html__( 'Exporter', 'iam-leads' )
		),
		esc_html__( 'Toutes les demandes reçues par les formulaires, avec leur statut de suivi.', 'iam-leads' )
	);
	?>

	<?php $m::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		class="iam-sec iam-export" id="iam-export-panneau"
		data-defaut="<?php echo esc_attr( implode( ',', IAM_Leads_Export::defaut() ) ); ?>" hidden>

		<input type="hidden" name="action" value="iam_leads_export">
		<?php wp_nonce_field( 'iam_leads_export' ); ?>
		<?php foreach ( $filtres_export as $cle => $valeur ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $cle ); ?>" value="<?php echo esc_attr( $valeur ); ?>">
		<?php endforeach; ?>

		<?php
		$m::sec_tete(
			'download',
			__( 'Colonnes à exporter', 'iam-leads' ),
			__( 'Les filtres actifs de la liste s\'appliquent au fichier. Votre choix est mémorisé.', 'iam-leads' ),
			sprintf(
				'<span class="iam-export-raccourcis"><button type="button" class="button" data-selection="tout">%1$s</button><button type="button" class="button" data-selection="rien">%2$s</button><button type="button" class="button" data-selection="defaut">%3$s</button></span>',
				esc_html__( 'Tout', 'iam-leads' ),
				esc_html__( 'Rien', 'iam-leads' ),
				esc_html__( 'Défaut', 'iam-leads' )
			)
		);
		?>

		<div class="iam-sec__body">
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
				<p class="iam-field__hint">
					<?php esc_html_e( 'Filtrez sur un formulaire pour pouvoir détailler ses réponses en colonnes séparées.', 'iam-leads' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="iam-sec__foot">
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
				<button type="submit" class="iam-adm-btn iam-adm-btn--sm">
					<span class="dashicons dashicons-download" aria-hidden="true"></span>
					<?php esc_html_e( 'Exporter', 'iam-leads' ); ?>
				</button>
			</p>
		</div>
	</form>

	<form method="get" class="iam-sec">
		<input type="hidden" name="page" value="iam-leads-list">
		<?php if ( $filtres['statut'] ) : ?>
			<input type="hidden" name="statut" value="<?php echo esc_attr( $filtres['statut'] ); ?>">
		<?php endif; ?>

		<div class="iam-sec__body">
			<div class="iam-filtres">
				<div class="iam-field iam-recherche">
					<label class="screen-reader-text" for="iam-recherche"><?php esc_html_e( 'Rechercher', 'iam-leads' ); ?></label>
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<input type="search" id="iam-recherche" name="recherche" value="<?php echo esc_attr( $filtres['recherche'] ); ?>"
						placeholder="<?php esc_attr_e( 'Nom, e-mail, téléphone…', 'iam-leads' ); ?>">
				</div>

				<div class="iam-field">
					<label class="screen-reader-text" for="iam-f-form"><?php esc_html_e( 'Formulaire', 'iam-leads' ); ?></label>
					<select id="iam-f-form" name="form_id">
						<option value=""><?php esc_html_e( 'Tous les formulaires', 'iam-leads' ); ?></option>
						<?php foreach ( IAM_Leads_Form_Builder::all() as $f ) : ?>
							<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $filtres['form_id'], $f->id ); ?>><?php echo esc_html( $f->nom ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="iam-field">
					<label class="screen-reader-text" for="iam-f-programme"><?php esc_html_e( 'Programme', 'iam-leads' ); ?></label>
					<select id="iam-f-programme" name="programme">
						<option value=""><?php esc_html_e( 'Tous les programmes', 'iam-leads' ); ?></option>
						<?php foreach ( $manager->valeurs_distinctes( 'programme' ) as $valeur ) : ?>
							<option value="<?php echo esc_attr( $valeur ); ?>" <?php selected( $filtres['programme'], $valeur ); ?>><?php echo esc_html( $valeur ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="iam-field">
					<label class="screen-reader-text" for="iam-f-source"><?php esc_html_e( 'Source', 'iam-leads' ); ?></label>
					<select id="iam-f-source" name="source">
						<option value=""><?php esc_html_e( 'Toutes les sources', 'iam-leads' ); ?></option>
						<?php foreach ( $manager->valeurs_distinctes( 'source' ) as $valeur ) : ?>
							<option value="<?php echo esc_attr( $valeur ); ?>" <?php selected( $filtres['source'], $valeur ); ?>><?php echo esc_html( $valeur ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="iam-field">
					<label class="screen-reader-text" for="iam-f-du"><?php esc_html_e( 'Du', 'iam-leads' ); ?></label>
					<input type="date" id="iam-f-du" name="du" value="<?php echo esc_attr( $filtres['du'] ); ?>" title="<?php esc_attr_e( 'Reçu à partir du', 'iam-leads' ); ?>">
				</div>

				<div class="iam-field">
					<label class="screen-reader-text" for="iam-f-au"><?php esc_html_e( 'Au', 'iam-leads' ); ?></label>
					<input type="date" id="iam-f-au" name="au" value="<?php echo esc_attr( $filtres['au'] ); ?>" title="<?php esc_attr_e( 'Reçu jusqu\'au', 'iam-leads' ); ?>">
				</div>

				<div class="iam-filtres__actions">
					<button type="submit" class="iam-adm-btn"><?php esc_html_e( 'Filtrer', 'iam-leads' ); ?></button>
					<?php if ( $filtres_plus ) : ?>
						<a class="iam-adm-icon" href="<?php echo esc_url( $filtres['statut'] ? add_query_arg( 'statut', $filtres['statut'], $url_liste ) : $url_liste ); ?>" title="<?php esc_attr_e( 'Réinitialiser les filtres', 'iam-leads' ); ?>">
							<span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php esc_html_e( 'Réinitialiser les filtres', 'iam-leads' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</form>

	<div class="iam-barre-liste">
		<nav class="iam-adm-tabs" aria-label="<?php esc_attr_e( 'Statut', 'iam-leads' ); ?>">
			<?php
			$base_onglets = add_query_arg( array_map( 'rawurlencode', $sans_statut ), $url_liste );
			$onglets      = array( '' => __( 'Tous', 'iam-leads' ) ) + $statuts;
			foreach ( $onglets as $cle => $libelle ) :
				$url = $cle ? add_query_arg( 'statut', $cle, $base_onglets ) : $base_onglets;
				?>
				<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $filtres['statut'] === $cle ? 'is-active' : ''; ?>"<?php echo $filtres['statut'] === $cle ? ' aria-current="page"' : ''; ?>>
					<?php if ( $cle ) : ?>
						<i class="iam-dot iam-dot--<?php echo esc_attr( $cle ); ?>" aria-hidden="true"></i>
					<?php endif; ?>
					<?php echo esc_html( $libelle ); ?>
					<span><?php echo esc_html( number_format_i18n( $compteurs[ $cle ] ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
	</div>

	<section class="iam-sec">
		<div class="iam-table-scroll">
			<table class="iam-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Nom', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Programme', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Source', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Reçu le', 'iam-leads' ); ?></th>
						<th class="iam-col-actions"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'iam-leads' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $resultat['items'] ) : ?>
					<tr><td colspan="6" class="iam-table-vide"><?php esc_html_e( 'Aucun lead ne correspond à ces filtres.', 'iam-leads' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $resultat['items'] as $lead ) : ?>
					<?php $url_detail = admin_url( 'admin.php?page=iam-leads-list&lead=' . $lead->id ); ?>
					<tr>
						<td class="iam-col-nom">
							<span class="iam-personne">
								<span class="iam-avatar" aria-hidden="true"><?php echo esc_html( iam_leads_initiales( $lead->nom_complet ) ); ?></span>
								<span>
									<a href="<?php echo esc_url( $url_detail ); ?>"><?php echo esc_html( $lead->nom_complet ? $lead->nom_complet : __( '(sans nom)', 'iam-leads' ) ); ?></a>
									<span class="iam-sous-ligne"><?php echo esc_html( $lead->email ); ?></span>
								</span>
							</span>
						</td>
						<td><?php echo esc_html( $lead->programme ? $lead->programme : '—' ); ?></td>
						<td>
							<?php echo esc_html( $lead->source ? $lead->source : '—' ); ?>
							<?php if ( $lead->utm_source ) : ?>
								<span class="iam-sous-ligne">utm : <?php echo esc_html( $lead->utm_source ); ?></span>
							<?php endif; ?>
						</td>
						<td><span class="iam-badge iam-badge-<?php echo esc_attr( $lead->statut ); ?>"><?php echo esc_html( $statuts[ $lead->statut ] ?? $lead->statut ); ?></span></td>
						<td class="iam-col-date"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $lead->date_creation ) ); ?></td>
						<td class="iam-col-actions">
							<a class="iam-adm-icon" href="<?php echo esc_url( $url_detail ); ?>" title="<?php esc_attr_e( 'Ouvrir la fiche', 'iam-leads' ); ?>">
								<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Ouvrir la fiche', 'iam-leads' ); ?></span>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="iam-pagination">
			<span class="iam-total">
				<?php
				printf(
					/* translators: %s : nombre de leads */
					wp_kses( _n( '<b>%s</b> lead', '<b>%s</b> leads', $resultat['total'], 'iam-leads' ), array( 'b' => array() ) ),
					esc_html( number_format_i18n( $resultat['total'] ) )
				);
				?>
			</span>
			<?php if ( $pages > 1 ) : ?>
				<span class="iam-pagination__pages">
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
				</span>
			<?php endif; ?>
		</div>
	</section>
</div>
