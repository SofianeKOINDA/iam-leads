<?php
/**
 * Tableau de bord.
 *
 * @package IAM_Leads
 *
 * @var array $data
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$periode  = sanitize_text_field( wp_unslash( $_GET['periode'] ?? '30j' ) ); // phpcs:ignore WordPress.Security.NonceVerification
$statuts  = IAM_Leads::statuts();
$cartes   = $data['cartes'];
$periodes = array(
	'7j'    => __( '7 j', 'iam-leads' ),
	'30j'   => __( '30 j', 'iam-leads' ),
	'3m'    => __( '3 mois', 'iam-leads' ),
	'1a'    => __( '1 an', 'iam-leads' ),
	'perso' => __( 'Personnalisé', 'iam-leads' ),
);
$m = 'IAM_Leads_Admin_Menu';

/**
 * Rend un indicateur d'évolution.
 *
 * La flèche double la couleur : l'information ne repose jamais sur la
 * teinte seule (contrainte d'accessibilité).
 *
 * @param float|null $valeur    Écart. Null = pas de référence exploitable.
 * @param string     $reference Libellé de la période de comparaison.
 * @param string     $unite     '%' ou 'pt', ou '' pour un écart brut.
 */
if ( ! function_exists( 'iam_leads_delta' ) ) :
	function iam_leads_delta( $valeur, $reference, $unite = '%' ) {
		// Un écart brut est un nombre de leads : pas de décimale.
		$decimales = '' === $unite ? 0 : 1;

		if ( null === $valeur ) {
			printf(
				'<span class="iam-delta iam-delta-plat">%s</span>',
				esc_html__( 'pas de référence', 'iam-leads' )
			);
			return;
		}

		$classe = 'iam-delta-plat';
		$fleche = '—';

		if ( $valeur > 0 ) {
			$classe = 'iam-delta-haut';
			$fleche = '▲';
		} elseif ( $valeur < 0 ) {
			$classe = 'iam-delta-bas';
			$fleche = '▼';
		}

		printf(
			'<span class="iam-delta %1$s">%2$s %3$s%4$s <span class="iam-delta-ref">%5$s</span></span>',
			esc_attr( $classe ),
			esc_html( $fleche ),
			esc_html( number_format_i18n( abs( $valeur ), $decimales ) ),
			esc_html( $unite ? ' ' . $unite : '' ),
			esc_html( $reference )
		);
	}
endif;

/**
 * Carte graphique : section-carte avec icône, aide et vue tableau.
 *
 * @param string $icone  Dashicon.
 * @param string $titre  Titre.
 * @param string $aide   Description.
 * @param string $canvas Identifiant du canvas.
 * @param string $source Clé des données pour la vue tableau ('' = aucune).
 * @param string $taille Classe de hauteur.
 */
if ( ! function_exists( 'iam_leads_carte_graph' ) ) :
	function iam_leads_carte_graph( $icone, $titre, $aide, $canvas, $source = '', $taille = 'iam-h-moyen' ) {
		?>
		<section class="iam-sec">
			<?php IAM_Leads_Admin_Menu::sec_tete( $icone, $titre, $aide ); ?>
			<div class="iam-sec__body">
				<div class="iam-zone-graph <?php echo esc_attr( $taille ); ?>"><canvas id="<?php echo esc_attr( $canvas ); ?>"></canvas></div>
				<?php if ( $source ) : ?>
					<details class="iam-vue-table" data-source="<?php echo esc_attr( $source ); ?>">
						<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
					</details>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}
endif;

ob_start();
?>
<form method="get" class="iam-periode">
	<input type="hidden" name="page" value="iam-leads-dashboard">
	<div class="iam-adm-tabs" role="group" aria-label="<?php esc_attr_e( 'Période analysée', 'iam-leads' ); ?>">
		<?php foreach ( $periodes as $cle => $libelle ) : ?>
			<button type="submit" name="periode" value="<?php echo esc_attr( $cle ); ?>" aria-pressed="<?php echo $periode === $cle ? 'true' : 'false'; ?>">
				<?php echo esc_html( $libelle ); ?>
			</button>
		<?php endforeach; ?>
	</div>
	<?php if ( 'perso' === $periode ) : ?>
		<span class="iam-periode-perso">
			<input type="date" name="du" value="<?php echo esc_attr( $data['bornes']['du'] ); ?>" aria-label="<?php esc_attr_e( 'Du', 'iam-leads' ); ?>">
			<input type="date" name="au" value="<?php echo esc_attr( $data['bornes']['au'] ); ?>" aria-label="<?php esc_attr_e( 'Au', 'iam-leads' ); ?>">
			<button type="submit" name="periode" value="perso" class="iam-adm-btn iam-adm-btn--sm"><?php esc_html_e( 'Appliquer', 'iam-leads' ); ?></button>
		</span>
	<?php endif; ?>
</form>
<?php
$filtre_periode = ob_get_clean();
?>
<div class="wrap iam-wrap iam-dashboard">
	<?php
	$m::entete(
		__( 'Pilotage', 'iam-leads' ),
		__( 'Tableau de bord', 'iam-leads' ),
		$filtre_periode,
		sprintf(
			/* translators: 1 : date de début, 2 : date de fin */
			esc_html__( 'Du %1$s au %2$s · comparé à la période précédente', 'iam-leads' ),
			esc_html( mysql2date( 'j F Y', $data['bornes']['du'] ) ),
			esc_html( mysql2date( 'j F Y', $data['bornes']['au'] ) )
		)
	);
	?>

	<?php $m::notice(); ?>

	<!-- Indicateurs : le premier, en vert, est le chiffre héros. -->
	<div class="iam-kpis">
		<div class="iam-kpi iam-kpi--hero">
			<div class="iam-kpi__head">
				<span class="iam-kpi__icon dashicons dashicons-groups" aria-hidden="true"></span>
				<p class="iam-kpi__label"><?php esc_html_e( 'Leads sur la période', 'iam-leads' ); ?></p>
			</div>
			<div class="iam-kpi__value"><?php echo esc_html( number_format_i18n( $cartes['total'] ) ); ?></div>
			<div class="iam-kpi__foot">
				<?php iam_leads_delta( $cartes['total_evol'], __( 'vs période préc.', 'iam-leads' ) ); ?>
				<svg class="iam-sparkline" data-spark="temporelle" width="84" height="26" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-kpi">
			<div class="iam-kpi__head">
				<span class="iam-kpi__icon dashicons dashicons-clock" aria-hidden="true"></span>
				<p class="iam-kpi__label"><?php esc_html_e( 'Aujourd\'hui', 'iam-leads' ); ?></p>
			</div>
			<div class="iam-kpi__value"><?php echo esc_html( number_format_i18n( $cartes['aujourdhui'] ) ); ?></div>
			<div class="iam-kpi__foot">
				<?php iam_leads_delta( (float) ( $cartes['aujourdhui'] - $cartes['hier'] ), __( 'vs hier', 'iam-leads' ), '' ); ?>
				<svg class="iam-sparkline" data-spark="jours" width="70" height="24" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-kpi">
			<div class="iam-kpi__head">
				<span class="iam-kpi__icon dashicons dashicons-calendar-alt" aria-hidden="true"></span>
				<p class="iam-kpi__label"><?php esc_html_e( 'Ce mois-ci', 'iam-leads' ); ?></p>
			</div>
			<div class="iam-kpi__value"><?php echo esc_html( number_format_i18n( $cartes['ce_mois'] ) ); ?></div>
			<div class="iam-kpi__foot">
				<?php iam_leads_delta( $cartes['ce_mois_evol'], __( 'vs mois préc.', 'iam-leads' ) ); ?>
				<svg class="iam-sparkline" data-spark="mois" width="70" height="24" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-kpi">
			<div class="iam-kpi__head">
				<span class="iam-kpi__icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<p class="iam-kpi__label"><?php esc_html_e( 'Taux de conversion', 'iam-leads' ); ?></p>
			</div>
			<div class="iam-kpi__value"><?php echo esc_html( number_format_i18n( $cartes['conversion'], 1 ) ); ?> %</div>
			<div class="iam-kpi__foot">
				<?php iam_leads_delta( $cartes['conversion_ecart'], __( 'vs période préc.', 'iam-leads' ), 'pt' ); ?>
			</div>
		</div>
	</div>

	<div class="iam-dash-grid iam-dash-grid--large">
		<section class="iam-sec">
			<?php
			$m::sec_tete(
				'chart-bar',
				__( 'Soumissions dans le temps', 'iam-leads' ),
				__( 'Survolez une barre pour lire le détail.', 'iam-leads' ),
				'<span class="iam-legende-comparaison"><b></b><span>' . esc_html__( 'Période', 'iam-leads' ) . '</span><i></i>' . esc_html__( 'Précédente', 'iam-leads' ) . '</span>'
			);
			?>
			<div class="iam-sec__body">
				<div class="iam-zone-graph iam-h-principal"><canvas id="iam-chart-temps"></canvas></div>
			</div>
		</section>

		<?php iam_leads_carte_graph( 'flag', __( 'Répartition par statut', 'iam-leads' ), __( 'Mêmes couleurs que les badges des leads.', 'iam-leads' ), 'iam-chart-statuts', 'statuts', 'iam-h-principal' ); ?>
	</div>

	<div class="iam-dash-grid iam-dash-grid--large">
		<?php iam_leads_carte_graph( 'welcome-learn-more', __( 'Programmes les plus demandés', 'iam-leads' ), __( 'Top 8 sur la période.', 'iam-leads' ), 'iam-chart-programmes', 'programmes' ); ?>
		<?php iam_leads_carte_graph( 'megaphone', __( 'Comment ont-ils connu l\'IAM ?', 'iam-leads' ), __( 'Top 8 sur la période.', 'iam-leads' ), 'iam-chart-sources', 'sources' ); ?>
	</div>

	<div class="iam-dash-grid iam-dash-grid--3">
		<?php iam_leads_carte_graph( 'admin-site-alt3', __( 'Pays de résidence', 'iam-leads' ), __( 'Top 8 sur la période.', 'iam-leads' ), 'iam-chart-pays', 'pays' ); ?>
		<?php iam_leads_carte_graph( 'share', __( 'Réseaux sociaux', 'iam-leads' ), __( 'Leads venus des réseaux.', 'iam-leads' ), 'iam-chart-reseaux', 'reseaux' ); ?>

		<!-- Part-à-tout : barre empilée, pas un camembert. -->
		<section class="iam-sec">
			<?php $m::sec_tete( 'phone', __( 'Contact préféré', 'iam-leads' ), __( 'Part de chaque canal.', 'iam-leads' ) ); ?>
			<div class="iam-sec__body">
				<div class="iam-barre-part" id="iam-barre-contact" role="img"></div>
				<ul class="iam-legende-part" id="iam-legende-contact"></ul>
				<details class="iam-vue-table" data-source="contact_prefere">
					<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
				</details>
			</div>
		</section>
	</div>

	<section class="iam-sec">
		<?php
		$m::sec_tete(
			'list-view',
			__( 'Dernières soumissions', 'iam-leads' ),
			__( 'Les leads les plus récents, tous formulaires confondus.', 'iam-leads' ),
			$data['dernieres'] ? sprintf(
				'<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--sm" href="%1$s">%2$s <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>',
				esc_url( admin_url( 'admin.php?page=iam-leads-list' ) ),
				esc_html__( 'Tous les leads', 'iam-leads' )
			) : ''
		);
		?>

		<?php if ( ! $data['dernieres'] ) : ?>
			<div class="iam-sec__body"><p class="iam-field__hint"><?php esc_html_e( 'Aucun lead reçu pour l\'instant. Publiez le formulaire sur une page pour commencer.', 'iam-leads' ); ?></p></div>
		<?php else : ?>
			<div class="iam-table-scroll">
				<table class="iam-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nom', 'iam-leads' ); ?></th>
							<th><?php esc_html_e( 'Programme', 'iam-leads' ); ?></th>
							<th><?php esc_html_e( 'Source', 'iam-leads' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'iam-leads' ); ?></th>
							<th><?php esc_html_e( 'Reçu le', 'iam-leads' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $data['dernieres'] as $ligne ) : ?>
						<?php $nom = $ligne->nom_complet ? $ligne->nom_complet : __( '(sans nom)', 'iam-leads' ); ?>
						<tr>
							<td class="iam-col-nom">
								<span class="iam-personne">
									<span class="iam-avatar" aria-hidden="true"><?php echo esc_html( iam_leads_initiales( $ligne->nom_complet ) ); ?></span>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list&lead=' . $ligne->id ) ); ?>"><?php echo esc_html( $nom ); ?></a>
								</span>
							</td>
							<td><?php echo esc_html( $ligne->programme ? $ligne->programme : '—' ); ?></td>
							<td><?php echo esc_html( $ligne->source ? $ligne->source : '—' ); ?></td>
							<td><span class="iam-badge iam-badge-<?php echo esc_attr( $ligne->statut ); ?>"><?php echo esc_html( $statuts[ $ligne->statut ] ?? $ligne->statut ); ?></span></td>
							<td class="iam-col-date"><?php echo esc_html( mysql2date( 'd/m H:i', $ligne->date_creation ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
</div>
