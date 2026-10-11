<?php
/**
 * Création / édition d'un formulaire.
 *
 * @package IAM_Leads
 *
 * @var object|null $form
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$est_nouveau = ! $form;
$champs      = $form ? $form->champs : array();
$plaquette   = $form && $form->plaquette_pdf ? wp_get_attachment_url( $form->plaquette_pdf ) : '';
?>
<div class="wrap iam-wrap">
	<h1><?php echo $est_nouveau ? esc_html__( 'Créer un formulaire', 'iam-leads' ) : esc_html__( 'Modifier le formulaire', 'iam-leads' ); ?></h1>

	<?php IAM_Leads_Admin_Menu::notice(); ?>

	<?php if ( ! $est_nouveau ) : ?>
		<p class="iam-shortcode-rappel">
			<?php esc_html_e( 'Insérez ce formulaire dans une page avec le shortcode :', 'iam-leads' ); ?>
			<code>[iam_form id="<?php echo esc_attr( $form->id ); ?>"]</code>
		</p>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="iam-form-edit">
		<input type="hidden" name="action" value="iam_save_form">
		<input type="hidden" name="id" value="<?php echo esc_attr( $form->id ?? 0 ); ?>">
		<?php // Nombre de champs envoyés : le serveur refuse d'enregistrer s'il en reçoit moins (requête tronquée par PHP). ?>
		<input type="hidden" name="champs_total" id="iam-champs-total" value="<?php echo esc_attr( count( $champs ) ); ?>">
		<?php wp_nonce_field( 'iam_save_form' ); ?>

		<div class="iam-colonnes">
			<div class="iam-colonne-principale">

				<div class="iam-carte">
					<h2><?php esc_html_e( 'Champs du formulaire', 'iam-leads' ); ?></h2>
					<p class="description">
						<?php esc_html_e( 'Un champ en demi-largeur se place sur deux colonnes en bureau et repasse sur une colonne en mobile.', 'iam-leads' ); ?>
					</p>

					<div class="notice notice-error iam-champs-alerte" id="iam-champs-alerte" hidden></div>

					<div id="iam-champs-liste">
						<?php foreach ( $champs as $index => $champ ) : ?>
							<?php
							$i = (int) $index;
							require IAM_LEADS_PATH . 'admin/views/partial-field.php';
							?>
						<?php endforeach; ?>
					</div>

					<p>
						<button type="button" class="button button-secondary" id="iam-ajouter-champ">
							<?php esc_html_e( 'Ajouter un champ', 'iam-leads' ); ?>
						</button>
					</p>
				</div>

			</div>

			<div class="iam-colonne-laterale">

				<div class="iam-carte">
					<h2><?php esc_html_e( 'Affichage', 'iam-leads' ); ?></h2>

					<?php $reglages = $form ? $form->reglages : IAM_Leads_Form_Builder::default_reglages(); ?>

					<p>
						<label for="iam-mode"><strong><?php esc_html_e( 'Mode', 'iam-leads' ); ?></strong></label>
						<select id="iam-mode" name="reglages[mode_affichage]" class="widefat">
							<?php foreach ( IAM_Leads_Form_Builder::modes_affichage() as $cle => $libelle ) : ?>
								<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $reglages['mode_affichage'], $cle ); ?>>
									<?php echo esc_html( $libelle ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="description">
							<?php esc_html_e( 'En mode étapes, ajoutez des champs « Séparateur d\'étape » entre les groupes de questions. Sans séparateur, le formulaire reste sur une page.', 'iam-leads' ); ?>
						</span>
					</p>

					<div id="iam-reglages-etapes">
						<p>
							<label for="iam-progression"><strong><?php esc_html_e( 'Indicateur de progression', 'iam-leads' ); ?></strong></label>
							<select id="iam-progression" name="reglages[progression]" class="widefat">
								<?php foreach ( IAM_Leads_Form_Builder::types_progression() as $cle => $libelle ) : ?>
									<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $reglages['progression'], $cle ); ?>>
										<?php echo esc_html( $libelle ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</p>

						<div class="iam-grille-2">
							<p>
								<label for="iam-bouton-precedent"><strong><?php esc_html_e( 'Bouton retour', 'iam-leads' ); ?></strong></label>
								<input type="text" id="iam-bouton-precedent" name="reglages[bouton_precedent]" class="widefat"
									value="<?php echo esc_attr( $reglages['bouton_precedent'] ); ?>">
							</p>
							<p>
								<label for="iam-bouton-suivant"><strong><?php esc_html_e( 'Bouton suivant', 'iam-leads' ); ?></strong></label>
								<input type="text" id="iam-bouton-suivant" name="reglages[bouton_suivant]" class="widefat"
									value="<?php echo esc_attr( $reglages['bouton_suivant'] ); ?>">
							</p>
						</div>
					</div>

					<hr>

					<div class="iam-grille-2">
						<p>
							<label for="iam-couleur-bouton"><strong><?php esc_html_e( 'Couleur du bouton', 'iam-leads' ); ?></strong></label>
							<input type="text" id="iam-couleur-bouton" class="iam-couleur" name="reglages[couleur_bouton]"
								value="<?php echo esc_attr( $reglages['couleur_bouton'] ); ?>"
								data-default-color="#1b4b8f">
						</p>
						<p>
							<label for="iam-couleur-bouton-texte"><strong><?php esc_html_e( 'Couleur du texte', 'iam-leads' ); ?></strong></label>
							<input type="text" id="iam-couleur-bouton-texte" class="iam-couleur" name="reglages[couleur_bouton_texte]"
								value="<?php echo esc_attr( $reglages['couleur_bouton_texte'] ); ?>"
								data-default-color="#ffffff">
						</p>
					</div>
					<p class="description">
						<?php esc_html_e( 'S\'applique au bouton d\'envoi et au bouton « Continuer ». Laissez vide, ou cliquez sur « Effacer », pour suivre la couleur principale du thème.', 'iam-leads' ); ?>
					</p>
				</div>

				<div class="iam-carte">
					<h2><?php esc_html_e( 'Réglages du formulaire', 'iam-leads' ); ?></h2>

					<p>
						<label for="iam-nom"><strong><?php esc_html_e( 'Nom du formulaire', 'iam-leads' ); ?></strong></label>
						<input type="text" id="iam-nom" name="nom" class="widefat" required
							value="<?php echo esc_attr( $form->nom ?? '' ); ?>">
					</p>

					<p>
						<label for="iam-bouton"><strong><?php esc_html_e( 'Texte du bouton', 'iam-leads' ); ?></strong></label>
						<input type="text" id="iam-bouton" name="bouton_texte" class="widefat"
							value="<?php echo esc_attr( $form->bouton_texte ?? 'Envoyer ma demande' ); ?>">
					</p>

					<p>
						<label for="iam-confirmation"><strong><?php esc_html_e( 'Message de confirmation', 'iam-leads' ); ?></strong></label>
						<textarea id="iam-confirmation" name="message_confirmation" class="widefat" rows="4"><?php echo esc_textarea( $form->message_confirmation ?? '' ); ?></textarea>
					</p>

					<p>
						<label for="iam-programme"><strong><?php esc_html_e( 'Programme associé', 'iam-leads' ); ?></strong></label>
						<select id="iam-programme" name="programme" class="widefat">
							<option value=""><?php esc_html_e( '— Aucun —', 'iam-leads' ); ?></option>
							<?php foreach ( IAM_Leads_Form_Builder::programmes() as $programme ) : ?>
								<option value="<?php echo esc_attr( $programme ); ?>" <?php selected( $form->programme ?? '', $programme ); ?>>
									<?php echo esc_html( $programme ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<span class="description">
							<?php esc_html_e( 'Utilisé quand le formulaire ne demande pas le programme (page dédiée à un seul programme).', 'iam-leads' ); ?>
						</span>
					</p>

					<p>
						<strong><?php esc_html_e( 'Plaquette par défaut', 'iam-leads' ); ?></strong><br>
						<input type="hidden" name="plaquette_pdf" id="iam-plaquette-id" value="<?php echo esc_attr( $form->plaquette_pdf ?? 0 ); ?>">
						<span id="iam-plaquette-nom" class="iam-fichier"><?php echo $plaquette ? esc_html( basename( $plaquette ) ) : esc_html__( 'Aucun fichier', 'iam-leads' ); ?></span><br>
						<button type="button" class="button" id="iam-choisir-plaquette"><?php esc_html_e( 'Choisir un PDF', 'iam-leads' ); ?></button>
						<button type="button" class="button" id="iam-retirer-plaquette"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></button>
						<span class="description">
							<?php esc_html_e( 'Une plaquette par programme se règle dans Réglages ; celle-ci sert de repli.', 'iam-leads' ); ?>
						</span>
					</p>

					<p>
						<button type="submit" class="button button-primary button-large">
							<?php esc_html_e( 'Enregistrer le formulaire', 'iam-leads' ); ?>
						</button>
					</p>
				</div>

			</div>
		</div>
	</form>
</div>

<script type="text/html" id="tmpl-iam-champ">
	<?php
	$i     = 0;
	$champ = array(
		'type'            => 'text',
		'label'           => '',
		'name'            => '',
		'placeholder'     => '',
		'required'        => 0,
		'options'         => array(),
		'width'           => 'full',
		'mapping'         => '',
		'valeur'          => '',
		'description'     => '',
		'label_prenom'    => '',
		'label_nom'       => '',
		'condition_field' => '',
		'condition_value' => '',
	);
	$gabarit = true;
	require IAM_LEADS_PATH . 'admin/views/partial-field.php';
	?>
</script>
