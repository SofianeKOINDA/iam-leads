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
?>
<div class="wrap iam-wrap iam-dashboard">

	<div class="iam-tete">
		<div class="iam-tete-titre">
			<h1><?php esc_html_e( 'Tableau de bord des leads', 'iam-leads' ); ?></h1>
			<p class="iam-tete-sous">
				<?php
				printf(
					/* translators: 1 : date de début, 2 : date de fin */
					esc_html__( 'Du %1$s au %2$s · comparé à la période précédente', 'iam-leads' ),
					esc_html( mysql2date( 'j F Y', $data['bornes']['du'] ) ),
					esc_html( mysql2date( 'j F Y', $data['bornes']['au'] ) )
				);
				?>
			</p>
		</div>

		<form method="get" class="iam-barre-filtres">
			<input type="hidden" name="page" value="iam-leads-dashboard">

			<div class="iam-segments" role="group" aria-label="<?php esc_attr_e( 'Période analysée', 'iam-leads' ); ?>">
				<?php foreach ( $periodes as $cle => $libelle ) : ?>
					<button type="submit" name="periode" value="<?php echo esc_attr( $cle ); ?>"
						aria-pressed="<?php echo $periode === $cle ? 'true' : 'false'; ?>">
						<?php echo esc_html( $libelle ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<?php if ( 'perso' === $periode ) : ?>
				<span class="iam-periode-perso">
					<input type="date" name="du" value="<?php echo esc_attr( $data['bornes']['du'] ); ?>">
					<input type="date" name="au" value="<?php echo esc_attr( $data['bornes']['au'] ); ?>">
					<button type="submit" name="periode" value="perso" class="iam-btn-fantome">
						<?php esc_html_e( 'Appliquer', 'iam-leads' ); ?>
					</button>
				</span>
			<?php endif; ?>
		</form>
	</div>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<!-- Rangée KPI : un chiffre héros, trois tuiles compactes. -->
	<div class="iam-rangee-kpi">

		<div class="iam-tuile iam-tuile-heros">
			<p class="iam-tuile-label"><?php esc_html_e( 'Leads sur la période', 'iam-leads' ); ?></p>
			<div class="iam-tuile-valeur"><?php echo esc_html( number_format_i18n( $cartes['total'] ) ); ?></div>
			<div class="iam-tuile-bas">
				<?php iam_leads_delta( $cartes['total_evol'], __( 'vs période préc.', 'iam-leads' ) ); ?>
				<svg class="iam-sparkline" data-spark="temporelle" width="112" height="30" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-tuile">
			<p class="iam-tuile-label"><?php esc_html_e( 'Aujourd\'hui', 'iam-leads' ); ?></p>
			<div class="iam-tuile-valeur"><?php echo esc_html( number_format_i18n( $cartes['aujourdhui'] ) ); ?></div>
			<div class="iam-tuile-bas">
				<?php iam_leads_delta( (float) ( $cartes['aujourdhui'] - $cartes['hier'] ), __( 'vs hier', 'iam-leads' ), '' ); ?>
				<svg class="iam-sparkline" data-spark="jours" width="70" height="24" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-tuile">
			<p class="iam-tuile-label"><?php esc_html_e( 'Ce mois-ci', 'iam-leads' ); ?></p>
			<div class="iam-tuile-valeur"><?php echo esc_html( number_format_i18n( $cartes['ce_mois'] ) ); ?></div>
			<div class="iam-tuile-bas">
				<?php iam_leads_delta( $cartes['ce_mois_evol'], __( 'vs mois préc.', 'iam-leads' ) ); ?>
				<svg class="iam-sparkline" data-spark="mois" width="70" height="24" aria-hidden="true"></svg>
			</div>
		</div>

		<div class="iam-tuile">
			<p class="iam-tuile-label"><?php esc_html_e( 'Taux de conversion', 'iam-leads' ); ?></p>
			<div class="iam-tuile-valeur"><?php echo esc_html( number_format_i18n( $cartes['conversion'], 1 ) ); ?> %</div>
			<div class="iam-tuile-bas">
				<?php iam_leads_delta( $cartes['conversion_ecart'], __( 'vs période préc.', 'iam-leads' ), 'pt' ); ?>
			</div>
		</div>

	</div>

	<!-- Graphique principal -->
	<div class="iam-carte">
		<div class="iam-carte-tete">
			<h2><?php esc_html_e( 'Soumissions dans le temps', 'iam-leads' ); ?></h2>
			<span class="iam-legende-comparaison"><?php esc_html_e( '— — période précédente', 'iam-leads' ); ?></span>
		</div>
		<p class="iam-aide"><?php esc_html_e( 'Survolez la courbe pour lire le détail.', 'iam-leads' ); ?></p>
		<div class="iam-zone-graph iam-h-principal"><canvas id="iam-chart-temps"></canvas></div>
	</div>

	<div class="iam-grille-graphs">

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Programmes les plus demandés', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Top 8 sur la période.', 'iam-leads' ); ?></p>
			<div class="iam-zone-graph iam-h-moyen"><canvas id="iam-chart-programmes"></canvas></div>
			<details class="iam-vue-table" data-source="programmes">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Comment ont-ils connu l\'IAM ?', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Top 8 sur la période.', 'iam-leads' ); ?></p>
			<div class="iam-zone-graph iam-h-moyen"><canvas id="iam-chart-sources"></canvas></div>
			<details class="iam-vue-table" data-source="sources">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Pays de résidence', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Top 8 sur la période.', 'iam-leads' ); ?></p>
			<div class="iam-zone-graph iam-h-moyen"><canvas id="iam-chart-pays"></canvas></div>
			<details class="iam-vue-table" data-source="pays">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Réseaux sociaux', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Parmi les leads venus des réseaux.', 'iam-leads' ); ?></p>
			<div class="iam-zone-graph iam-h-moyen"><canvas id="iam-chart-reseaux"></canvas></div>
			<details class="iam-vue-table" data-source="reseaux">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

		<!-- Part-à-tout : barre empilée, pas un camembert. -->
		<div class="iam-carte">
			<h2><?php esc_html_e( 'Moyen de contact préféré', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Part de chaque canal sur la période.', 'iam-leads' ); ?></p>
			<div class="iam-barre-part" id="iam-barre-contact" role="img"></div>
			<ul class="iam-legende-part" id="iam-legende-contact"></ul>
			<details class="iam-vue-table" data-source="contact_prefere">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

		<div class="iam-carte">
			<h2><?php esc_html_e( 'Répartition par statut', 'iam-leads' ); ?></h2>
			<p class="iam-aide"><?php esc_html_e( 'Mêmes couleurs que les badges de la liste des leads.', 'iam-leads' ); ?></p>
			<div class="iam-zone-graph iam-h-moyen"><canvas id="iam-chart-statuts"></canvas></div>
			<details class="iam-vue-table" data-source="statuts">
				<summary><?php esc_html_e( 'Voir les données', 'iam-leads' ); ?></summary>
			</details>
		</div>

	</div>

	<div class="iam-carte iam-carte-derniers">
		<h2><?php esc_html_e( 'Dernières soumissions', 'iam-leads' ); ?></h2>

		<?php if ( ! $data['dernieres'] ) : ?>
			<p class="iam-aide"><?php esc_html_e( 'Aucun lead reçu pour l\'instant. Publiez le formulaire sur une page pour commencer.', 'iam-leads' ); ?></p>
		<?php else : ?>
			<p class="iam-aide"><?php esc_html_e( 'Les leads les plus récents, tous formulaires confondus.', 'iam-leads' ); ?></p>
			<table class="iam-table-leads">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Nom', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Programme', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Source', 'iam-leads' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'iam-leads' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $data['dernieres'] as $ligne ) : ?>
					<tr>
						<td class="iam-col-date"><?php echo esc_html( mysql2date( 'd/m H:i', $ligne->date_creation ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list&lead=' . $ligne->id ) ); ?>">
								<?php echo esc_html( $ligne->nom_complet ? $ligne->nom_complet : __( '(sans nom)', 'iam-leads' ) ); ?>
							</a>
						</td>
						<td><?php echo esc_html( $ligne->programme ? $ligne->programme : '—' ); ?></td>
						<td><?php echo esc_html( $ligne->source ? $ligne->source : '—' ); ?></td>
						<td>
							<span class="iam-badge iam-badge-<?php echo esc_attr( $ligne->statut ); ?>">
								<i aria-hidden="true"></i><?php echo esc_html( $statuts[ $ligne->statut ] ?? $ligne->statut ); ?>
							</span>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="iam-pied-carte">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-list' ) ); ?>">
					<?php esc_html_e( 'Voir tous les leads →', 'iam-leads' ); ?>
				</a>
			</div>
		<?php endif; ?>
	</div>

</div>
