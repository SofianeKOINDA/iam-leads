<?php
/**
 * Création / édition d'un formulaire.
 *
 * Même gabarit que l'assistant de IAM Certifications : sections-cartes à
 * icône, champs libellés au-dessus, pied collant avec l'enregistrement.
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
$reglages    = $form ? $form->reglages : IAM_Leads_Form_Builder::default_reglages();
$m           = 'IAM_Leads_Admin_Menu';

$actions = '';
if ( ! $est_nouveau ) {
	$actions = sprintf(
		'<button type="button" class="iam-copiable" title="%1$s">[iam_form id="%2$d"]</button> <a class="iam-adm-btn iam-adm-btn--ghost" href="%3$s"><span class="dashicons dashicons-groups" aria-hidden="true"></span> %4$s</a>',
		esc_attr__( 'Copier le shortcode', 'iam-leads' ),
		(int) $form->id,
		esc_url( admin_url( 'admin.php?page=iam-leads-list&form_id=' . $form->id ) ),
		esc_html__( 'Voir les leads', 'iam-leads' )
	);
}
?>
<div class="wrap iam-wrap">
	<?php
	$m::entete(
		__( 'Formulaires', 'iam-leads' ),
		$est_nouveau ? __( 'Nouveau formulaire', 'iam-leads' ) : $form->nom,
		$actions,
		'',
		admin_url( 'admin.php?page=iam-leads-forms' )
	);
	?>
	<?php $m::notice(); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="iam-form-edit">
		<input type="hidden" name="action" value="iam_save_form">
		<input type="hidden" name="id" value="<?php echo esc_attr( $form->id ?? 0 ); ?>">
		<?php // Nombre de champs envoyés : le serveur refuse d'enregistrer s'il en reçoit moins (requête tronquée par PHP). ?>
		<input type="hidden" name="champs_total" id="iam-champs-total" value="<?php echo esc_attr( count( $champs ) ); ?>">
		<?php wp_nonce_field( 'iam_save_form' ); ?>

		<div class="iam-layout">
			<div class="iam-layout__main">

				<section class="iam-sec">
					<?php $m::sec_tete( 'id-alt', __( 'Identité', 'iam-leads' ), __( 'Le nom sert à retrouver le formulaire ; il n\'est pas affiché aux visiteurs.', 'iam-leads' ) ); ?>
					<div class="iam-sec__body">
						<div class="iam-fields">
							<div class="iam-field iam-field--full">
								<label class="iam-field__label" for="iam-nom"><?php esc_html_e( 'Nom du formulaire', 'iam-leads' ); ?> <span class="iam-req">*</span></label>
								<input type="text" id="iam-nom" name="nom" class="iam-input-xl" required
									value="<?php echo esc_attr( $form->nom ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Ex. : Demande d\'information — Master', 'iam-leads' ); ?>">
							</div>
							<div class="iam-field">
								<label class="iam-field__label" for="iam-bouton"><?php esc_html_e( 'Texte du bouton d\'envoi', 'iam-leads' ); ?></label>
								<input type="text" id="iam-bouton" name="bouton_texte" value="<?php echo esc_attr( $form->bouton_texte ?? 'Envoyer ma demande' ); ?>">
							</div>
							<div class="iam-field">
								<label class="iam-field__label" for="iam-programme"><?php esc_html_e( 'Programme associé', 'iam-leads' ); ?></label>
								<select id="iam-programme" name="programme">
									<option value=""><?php esc_html_e( '— Aucun —', 'iam-leads' ); ?></option>
									<?php foreach ( IAM_Leads_Form_Builder::programmes() as $programme ) : ?>
										<option value="<?php echo esc_attr( $programme ); ?>" <?php selected( $form->programme ?? '', $programme ); ?>><?php echo esc_html( $programme ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="iam-field__hint"><?php esc_html_e( 'Utilisé quand le formulaire ne demande pas le programme (page d\'un seul programme).', 'iam-leads' ); ?></p>
							</div>
						</div>
					</div>
				</section>

				<section class="iam-sec">
					<?php
					$m::sec_tete(
						'forms',
						__( 'Champs du formulaire', 'iam-leads' ),
						__( 'Cliquez sur « Ouvrir » pour régler un champ. Deux champs en demi-largeur se placent côte à côte sur ordinateur.', 'iam-leads' ),
						'<span class="iam-adm-pill iam-adm-pill--neutre" id="iam-champs-compte">' . esc_html( sprintf( _n( '%d champ', '%d champs', count( $champs ), 'iam-leads' ), count( $champs ) ) ) . '</span>'
					);
					?>
					<div class="iam-sec__body">
						<div class="notice notice-error iam-champs-alerte" id="iam-champs-alerte" hidden></div>

						<div id="iam-champs-liste">
							<?php foreach ( $champs as $index => $champ ) : ?>
								<?php
								$i = (int) $index;
								require IAM_LEADS_PATH . 'admin/views/partial-field.php';
								?>
							<?php endforeach; ?>
						</div>

						<button type="button" class="iam-ajout-champ" id="iam-ajouter-champ">
							<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
							<?php esc_html_e( 'Ajouter un champ', 'iam-leads' ); ?>
						</button>
					</div>
				</section>

			</div>

			<aside class="iam-layout__side">

				<section class="iam-sec">
					<?php
					$par_defaut = IAM_Leads_Form_Builder::adresses( IAM_Leads::get_setting( 'admin_emails' ) );
					$m::sec_tete( 'email-alt', __( 'Qui reçoit les leads', 'iam-leads' ), __( 'Personnes prévenues par e-mail à chaque demande reçue par ce formulaire.', 'iam-leads' ) );
					?>
					<div class="iam-sec__body">
						<div class="iam-field">
							<label class="iam-field__label" for="iam-destinataires"><?php esc_html_e( 'Destinataires de ce formulaire', 'iam-leads' ); ?></label>
							<textarea id="iam-destinataires" name="reglages[destinataires]" rows="2" placeholder="banel@groupeiam.com"><?php echo esc_textarea( str_replace( ', ', "\n", (string) ( $reglages['destinataires'] ?? '' ) ) ); ?></textarea>
							<p class="iam-field__hint">
								<?php esc_html_e( 'Une adresse par ligne (ou séparées par des virgules).', 'iam-leads' ); ?>
								<?php if ( $par_defaut ) : ?>
									<?php
									printf(
										/* translators: %s : adresses par défaut */
										esc_html__( 'Laissé vide : les destinataires par défaut (%s).', 'iam-leads' ),
										esc_html( implode( ', ', $par_defaut ) )
									);
									?>
								<?php else : ?>
									<?php esc_html_e( 'Laissé vide : les destinataires par défaut de Réglages → E-mails (aucun pour l\'instant).', 'iam-leads' ); ?>
								<?php endif; ?>
							</p>
						</div>
						<?php echo $m::interrupteur( 'reglages[copie_defaut]', ! empty( $reglages['copie_defaut'] ), __( 'Prévenir aussi les destinataires par défaut', 'iam-leads' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>

				<section class="iam-sec">
					<?php $m::sec_tete( 'yes-alt', __( 'Après l\'envoi', 'iam-leads' ), __( 'Ce que voit le visiteur une fois sa demande envoyée.', 'iam-leads' ) ); ?>
					<div class="iam-sec__body">
						<div class="iam-field">
							<label class="iam-field__label" for="iam-confirmation"><?php esc_html_e( 'Message de confirmation', 'iam-leads' ); ?></label>
							<textarea id="iam-confirmation" name="message_confirmation" rows="4"><?php echo esc_textarea( $form->message_confirmation ?? '' ); ?></textarea>
						</div>
						<div class="iam-field">
							<span class="iam-field__label"><?php esc_html_e( 'Plaquette par défaut', 'iam-leads' ); ?></span>
							<input type="hidden" name="plaquette_pdf" id="iam-plaquette-id" value="<?php echo esc_attr( $form->plaquette_pdf ?? 0 ); ?>">
							<div class="iam-fichier-ligne">
								<span id="iam-plaquette-nom" class="iam-fichier"><?php echo $plaquette ? esc_html( basename( $plaquette ) ) : esc_html__( 'Aucun fichier', 'iam-leads' ); ?></span>
								<button type="button" class="button" id="iam-choisir-plaquette"><?php esc_html_e( 'Choisir un PDF', 'iam-leads' ); ?></button>
								<button type="button" class="button" id="iam-retirer-plaquette"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></button>
							</div>
							<p class="iam-field__hint"><?php esc_html_e( 'Repli si le programme choisi n\'a pas sa propre plaquette (Réglages → Plaquettes).', 'iam-leads' ); ?></p>
						</div>
					</div>
				</section>

				<section class="iam-sec">
					<?php $m::sec_tete( 'admin-appearance', __( 'Affichage', 'iam-leads' ), __( 'Une page ou plusieurs étapes, couleurs du bouton.', 'iam-leads' ) ); ?>
					<div class="iam-sec__body">
						<div class="iam-field">
							<label class="iam-field__label" for="iam-mode"><?php esc_html_e( 'Mode', 'iam-leads' ); ?></label>
							<select id="iam-mode" name="reglages[mode_affichage]">
								<?php foreach ( IAM_Leads_Form_Builder::modes_affichage() as $cle => $libelle ) : ?>
									<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $reglages['mode_affichage'], $cle ); ?>><?php echo esc_html( $libelle ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="iam-field__hint"><?php esc_html_e( 'En mode étapes, ajoutez des champs « Séparateur d\'étape » entre les groupes de questions.', 'iam-leads' ); ?></p>
						</div>

						<div id="iam-reglages-etapes" class="iam-fields">
							<div class="iam-field iam-field--full">
								<label class="iam-field__label" for="iam-progression"><?php esc_html_e( 'Indicateur de progression', 'iam-leads' ); ?></label>
								<select id="iam-progression" name="reglages[progression]">
									<?php foreach ( IAM_Leads_Form_Builder::types_progression() as $cle => $libelle ) : ?>
										<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $reglages['progression'], $cle ); ?>><?php echo esc_html( $libelle ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="iam-field">
								<label class="iam-field__label" for="iam-bouton-precedent"><?php esc_html_e( 'Bouton retour', 'iam-leads' ); ?></label>
								<input type="text" id="iam-bouton-precedent" name="reglages[bouton_precedent]" value="<?php echo esc_attr( $reglages['bouton_precedent'] ); ?>">
							</div>
							<div class="iam-field">
								<label class="iam-field__label" for="iam-bouton-suivant"><?php esc_html_e( 'Bouton suivant', 'iam-leads' ); ?></label>
								<input type="text" id="iam-bouton-suivant" name="reglages[bouton_suivant]" value="<?php echo esc_attr( $reglages['bouton_suivant'] ); ?>">
							</div>
						</div>

						<div class="iam-fields">
							<div class="iam-field iam-field--full">
								<label class="iam-field__label" for="iam-couleur-bouton"><?php esc_html_e( 'Couleur du bouton', 'iam-leads' ); ?></label>
								<input type="text" id="iam-couleur-bouton" class="iam-couleur" name="reglages[couleur_bouton]"
									value="<?php echo esc_attr( $reglages['couleur_bouton'] ); ?>" data-default-color="#1b4b8f">
							</div>
							<div class="iam-field iam-field--full">
								<label class="iam-field__label" for="iam-couleur-bouton-texte"><?php esc_html_e( 'Couleur du texte', 'iam-leads' ); ?></label>
								<input type="text" id="iam-couleur-bouton-texte" class="iam-couleur" name="reglages[couleur_bouton_texte]"
									value="<?php echo esc_attr( $reglages['couleur_bouton_texte'] ); ?>" data-default-color="#ffffff">
							</div>
						</div>
						<p class="iam-field__hint"><?php esc_html_e( 'Laissez vide (bouton « Effacer ») pour garder les couleurs IAM : bouton or, texte foncé.', 'iam-leads' ); ?></p>
					</div>
				</section>

			</aside>
		</div>

		<div class="iam-wiz-foot">
			<p class="iam-wiz-foot__hint"><?php esc_html_e( 'Les champs marqués * sont requis. Rien n\'est enregistré tant que vous n\'avez pas cliqué sur Enregistrer.', 'iam-leads' ); ?></p>
			<div class="iam-wiz-foot__right">
				<a class="iam-adm-btn iam-adm-btn--ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=iam-leads-forms' ) ); ?>"><?php esc_html_e( 'Annuler', 'iam-leads' ); ?></a>
				<button type="submit" class="iam-adm-btn">
					<span class="dashicons dashicons-saved" aria-hidden="true"></span>
					<?php esc_html_e( 'Enregistrer le formulaire', 'iam-leads' ); ?>
				</button>
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
