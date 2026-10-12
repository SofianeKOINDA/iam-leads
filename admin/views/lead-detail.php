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
$m         = 'IAM_Leads_Admin_Menu';

/**
 * Valeur affichable : tiret discret si vide, lien si e-mail ou URL.
 *
 * @param string $valeur Valeur brute.
 */
$afficher = static function ( $valeur ) {
	$valeur = (string) $valeur;

	if ( '' === $valeur ) {
		echo '<span class="iam-vide-inline">—</span>';
	} elseif ( is_email( $valeur ) ) {
		printf( '<a href="mailto:%1$s">%1$s</a>', esc_attr( $valeur ) );
	} elseif ( preg_match( '#^https?://#i', $valeur ) ) {
		printf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $valeur ), esc_html( $valeur ) );
	} else {
		echo esc_html( $valeur );
	}
};

$actions = sprintf(
	'<span class="iam-badge iam-badge-%1$s">%2$s</span>',
	esc_attr( $lead->statut ),
	esc_html( $statuts[ $lead->statut ] ?? $lead->statut )
);
?>
<div class="wrap iam-wrap">
	<?php
	$m::entete(
		__( 'Leads', 'iam-leads' ),
		$lead->nom_complet ? $lead->nom_complet : __( 'Lead sans nom', 'iam-leads' ),
		$actions,
		sprintf(
			/* translators: 1 : date, 2 : nom du formulaire */
			esc_html__( 'Reçu le %1$s via « %2$s »', 'iam-leads' ),
			esc_html( mysql2date( 'd/m/Y à H:i', $lead->date_creation ) ),
			esc_html( $form ? $form->nom : __( 'formulaire supprimé', 'iam-leads' ) )
		),
		admin_url( 'admin.php?page=iam-leads-list' )
	);
	?>

	<?php $m::notice(); ?>

	<div class="iam-layout">
		<div class="iam-layout__main">

			<section class="iam-sec">
				<?php $m::sec_tete( 'feedback', __( 'Réponses au formulaire', 'iam-leads' ), __( 'Tout ce que le prospect a saisi.', 'iam-leads' ) ); ?>
				<div class="iam-sec__body">
					<dl class="iam-def">
						<?php foreach ( $lead->donnees_decodees as $cle => $champ ) : ?>
							<?php
							// Format courant : { label, valeur }. Un lead importé à
							// plat (clé => valeur) reste lisible.
							if ( ! is_array( $champ ) ) {
								$champ = array(
									'label'  => ucfirst( str_replace( '_', ' ', (string) $cle ) ),
									'valeur' => $champ,
								);
							}
							$champ['valeur'] = is_array( $champ['valeur'] ?? '' ) ? implode( ', ', $champ['valeur'] ) : (string) ( $champ['valeur'] ?? '' );
							$long            = strlen( $champ['valeur'] ) > 60;
							?>
							<div<?php echo $long ? ' class="iam-def--full"' : ''; ?>>
								<dt><?php echo esc_html( $champ['label'] ?? '' ); ?></dt>
								<dd><?php $afficher( $champ['valeur'] ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>
				</div>
			</section>

			<section class="iam-sec">
				<?php $m::sec_tete( 'location-alt', __( 'Provenance', 'iam-leads' ), __( 'Campagne et pages par lesquelles le prospect est arrivé.', 'iam-leads' ) ); ?>
				<div class="iam-sec__body">
					<dl class="iam-def">
						<div><dt>utm_source</dt><dd><?php $afficher( $lead->utm_source ); ?></dd></div>
						<div><dt>utm_medium</dt><dd><?php $afficher( $lead->utm_medium ); ?></dd></div>
						<div><dt>utm_campaign</dt><dd><?php $afficher( $lead->utm_campaign ); ?></dd></div>
						<div><dt>utm_content</dt><dd><?php $afficher( $lead->utm_content ); ?></dd></div>
						<div><dt>utm_term</dt><dd><?php $afficher( $lead->utm_term ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Adresse IP', 'iam-leads' ); ?></dt><dd><?php $afficher( $lead->ip ); ?></dd></div>
						<div class="iam-def--full"><dt><?php esc_html_e( 'Page d\'origine', 'iam-leads' ); ?></dt><dd><?php $afficher( $lead->page_origine ); ?></dd></div>
						<div class="iam-def--full"><dt><?php esc_html_e( 'Page du formulaire', 'iam-leads' ); ?></dt><dd><?php $afficher( $lead->page_soumission ); ?></dd></div>
						<div class="iam-def--full"><dt><?php esc_html_e( 'Navigateur', 'iam-leads' ); ?></dt><dd><?php $afficher( $lead->user_agent ); ?></dd></div>
					</dl>
				</div>
			</section>

		</div>

		<aside class="iam-layout__side iam-layout__side--sticky">

			<section class="iam-sec">
				<?php $m::sec_tete( 'flag', __( 'Suivi', 'iam-leads' ) ); ?>
				<form class="iam-sec__body" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="iam_update_lead">
					<input type="hidden" name="id" value="<?php echo esc_attr( $lead->id ); ?>">
					<?php wp_nonce_field( 'iam_update_lead' ); ?>

					<div class="iam-field">
						<label class="iam-field__label" for="iam-statut"><?php esc_html_e( 'Statut du lead', 'iam-leads' ); ?></label>
						<select id="iam-statut" name="statut">
							<?php foreach ( $statuts as $cle => $libelle ) : ?>
								<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $lead->statut, $cle ); ?>><?php echo esc_html( $libelle ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<button type="submit" class="iam-adm-btn iam-adm-btn--block"><?php esc_html_e( 'Enregistrer le statut', 'iam-leads' ); ?></button>
				</form>
			</section>

			<section class="iam-sec">
				<?php $m::sec_tete( 'phone', __( 'Contacter', 'iam-leads' ) ); ?>
				<div class="iam-sec__body">
					<?php if ( $lead->email ) : ?>
						<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--block" href="mailto:<?php echo esc_attr( $lead->email ); ?>">
							<span class="dashicons dashicons-email" aria-hidden="true"></span> <?php echo esc_html( $lead->email ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $lead->telephone ) : ?>
						<a class="iam-adm-btn iam-adm-btn--ghost iam-adm-btn--block" target="_blank" rel="noopener"
							href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $lead->telephone ) ); ?>">
							<span class="dashicons dashicons-whatsapp" aria-hidden="true"></span> <?php esc_html_e( 'Conversation WhatsApp', 'iam-leads' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( ! $lead->email && ! $lead->telephone ) : ?>
						<p class="iam-field__hint"><?php esc_html_e( 'Ni e-mail ni téléphone renseigné.', 'iam-leads' ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="iam-sec">
				<?php $m::sec_tete( 'update', __( 'Envois automatiques', 'iam-leads' ) ); ?>
				<div class="iam-sec__body">
					<dl class="iam-def iam-def--1">
						<div>
							<dt><?php esc_html_e( 'CRM', 'iam-leads' ); ?></dt>
							<dd><span class="iam-badge iam-badge-<?php echo esc_attr( $lead->crm_status ); ?>"><?php echo esc_html( $lead->crm_status ); ?></span></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'WhatsApp', 'iam-leads' ); ?></dt>
							<dd><span class="iam-badge iam-badge-<?php echo esc_attr( $lead->whatsapp_status ); ?>"><?php echo esc_html( $lead->whatsapp_status ); ?></span></dd>
						</div>
					</dl>
				</div>
				<div class="iam-sec__foot">
					<a class="iam-adm-btn iam-adm-btn--danger iam-adm-btn--sm" href="<?php echo esc_url( $url_suppr ); ?>"
						onclick="return confirm('<?php echo esc_js( __( 'Supprimer ce lead définitivement ?', 'iam-leads' ) ); ?>');">
						<span class="dashicons dashicons-trash" aria-hidden="true"></span>
						<?php esc_html_e( 'Supprimer ce lead', 'iam-leads' ); ?>
					</a>
				</div>
			</section>

		</aside>
	</div>
</div>
