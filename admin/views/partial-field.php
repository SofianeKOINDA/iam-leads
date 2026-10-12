<?php
/**
 * Bloc d'édition d'un champ.
 * Réutilisé pour les champs existants et pour le gabarit JavaScript.
 *
 * Les classes iam-champ-* servent de points d'accroche à form-builder.js
 * (affichage selon le type) : ne pas les renommer.
 *
 * @package IAM_Leads
 *
 * @var array $champ   Définition du champ.
 * @var int   $i       Index dans le tableau.
 * @var bool  $gabarit Rendu du gabarit JS (index remplacé à la volée).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$gabarit = isset( $gabarit ) ? (bool) $gabarit : false;
$idx     = $gabarit ? '__INDEX__' : (int) $i;
$champ   = wp_parse_args(
	$champ,
	array(
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
	)
);
$base = 'champs[' . $idx . ']';
$uid  = 'iam-c-' . $idx;
?>
<div class="iam-champ" data-index="<?php echo esc_attr( $idx ); ?>">
	<div class="iam-champ-entete">
		<span class="iam-champ-poignee dashicons dashicons-menu-alt" aria-hidden="true"></span>
		<strong class="iam-champ-titre"><?php echo esc_html( $champ['label'] ? $champ['label'] : __( 'Nouveau champ', 'iam-leads' ) ); ?></strong>
		<span class="iam-champ-type"><?php echo esc_html( IAM_Leads::types_champs()[ $champ['type'] ] ?? $champ['type'] ); ?></span>
		<span class="iam-champ-actions">
			<button type="button" class="button iam-monter" title="<?php esc_attr_e( 'Monter', 'iam-leads' ); ?>">↑</button>
			<button type="button" class="button iam-descendre" title="<?php esc_attr_e( 'Descendre', 'iam-leads' ); ?>">↓</button>
			<button type="button" class="button iam-basculer"><?php esc_html_e( 'Ouvrir', 'iam-leads' ); ?></button>
			<button type="button" class="button iam-danger iam-supprimer"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></button>
		</span>
	</div>

	<div class="iam-champ-corps" hidden>
		<div class="iam-fields">
			<div class="iam-field">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-type"><?php esc_html_e( 'Type', 'iam-leads' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-type" name="<?php echo esc_attr( $base ); ?>[type]" class="iam-champ-type-select">
					<?php foreach ( IAM_Leads::types_champs() as $cle => $libelle ) : ?>
						<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $champ['type'], $cle ); ?>><?php echo esc_html( $libelle ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="iam-field iam-champ-largeur">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-largeur"><?php esc_html_e( 'Largeur', 'iam-leads' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-largeur" name="<?php echo esc_attr( $base ); ?>[width]">
					<option value="full" <?php selected( $champ['width'], 'full' ); ?>><?php esc_html_e( 'Pleine largeur', 'iam-leads' ); ?></option>
					<option value="half" <?php selected( $champ['width'], 'half' ); ?>><?php esc_html_e( 'Demi-largeur', 'iam-leads' ); ?></option>
				</select>
			</div>

			<div class="iam-field">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-label">
					<?php esc_html_e( 'Étiquette', 'iam-leads' ); ?> <span class="iam-oblig" aria-hidden="true">*</span>
				</label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-label" class="iam-champ-label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $champ['label'] ); ?>">
			</div>

			<div class="iam-field iam-champ-identifiant">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Identifiant technique', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-name" class="iam-champ-name" name="<?php echo esc_attr( $base ); ?>[name]" value="<?php echo esc_attr( $champ['name'] ); ?>">
				<p class="iam-field__hint"><?php esc_html_e( 'Généré depuis l\'étiquette.', 'iam-leads' ); ?></p>
			</div>

			<div class="iam-field iam-champ-placeholder">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-placeholder"><?php esc_html_e( 'Texte indicatif', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-placeholder" name="<?php echo esc_attr( $base ); ?>[placeholder]" value="<?php echo esc_attr( $champ['placeholder'] ); ?>">
			</div>

			<div class="iam-field iam-champ-mapping">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-mapping"><?php esc_html_e( 'Correspondance CRM', 'iam-leads' ); ?></label>
				<select id="<?php echo esc_attr( $uid ); ?>-mapping" name="<?php echo esc_attr( $base ); ?>[mapping]">
					<?php foreach ( IAM_Leads::mappings() as $cle => $libelle ) : ?>
						<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $champ['mapping'], $cle ); ?>><?php echo esc_html( $libelle ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="iam-field__hint"><?php esc_html_e( 'Colonne utilisée pour les filtres, le tableau de bord et le CRM.', 'iam-leads' ); ?></p>
			</div>

			<div class="iam-field iam-field--full iam-champ-options">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-options"><?php esc_html_e( 'Options', 'iam-leads' ); ?></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-options" rows="4" name="<?php echo esc_attr( $base ); ?>[options]"><?php echo esc_textarea( implode( "\n", (array) $champ['options'] ) ); ?></textarea>
				<p class="iam-field__hint"><?php esc_html_e( 'Une option par ligne (listes, boutons radio, cases à cocher).', 'iam-leads' ); ?></p>
			</div>

			<div class="iam-field iam-champ-sous-labels">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-prenom"><?php esc_html_e( 'Libellé du prénom', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-prenom" name="<?php echo esc_attr( $base ); ?>[label_prenom]" value="<?php echo esc_attr( $champ['label_prenom'] ); ?>" placeholder="<?php esc_attr_e( 'Prénom', 'iam-leads' ); ?>">
			</div>
			<div class="iam-field iam-champ-sous-labels">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-nomfam"><?php esc_html_e( 'Libellé du nom de famille', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-nomfam" name="<?php echo esc_attr( $base ); ?>[label_nom]" value="<?php echo esc_attr( $champ['label_nom'] ); ?>" placeholder="<?php esc_attr_e( 'Nom de famille', 'iam-leads' ); ?>">
			</div>

			<div class="iam-field iam-field--full iam-champ-description">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-description"><?php esc_html_e( 'Texte d\'introduction de l\'étape', 'iam-leads' ); ?></label>
				<textarea id="<?php echo esc_attr( $uid ); ?>-description" rows="3" name="<?php echo esc_attr( $base ); ?>[description]"><?php echo esc_textarea( $champ['description'] ); ?></textarea>
				<p class="iam-field__hint"><?php esc_html_e( 'Facultatif. S\'affiche sous le titre de l\'étape ; l\'étiquette sert de titre.', 'iam-leads' ); ?></p>
			</div>

			<div class="iam-field iam-field--full iam-champ-valeur">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-valeur"><?php esc_html_e( 'Valeur du champ caché', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-valeur" name="<?php echo esc_attr( $base ); ?>[valeur]" value="<?php echo esc_attr( $champ['valeur'] ); ?>">
			</div>

			<div class="iam-field iam-champ-condition">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-cond-champ"><?php esc_html_e( 'Afficher si le champ', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-cond-champ" name="<?php echo esc_attr( $base ); ?>[condition_field]" value="<?php echo esc_attr( $champ['condition_field'] ); ?>" placeholder="<?php esc_attr_e( 'identifiant technique', 'iam-leads' ); ?>">
			</div>
			<div class="iam-field iam-champ-condition">
				<label class="iam-field__label" for="<?php echo esc_attr( $uid ); ?>-cond-valeur"><?php esc_html_e( 'a pour valeur', 'iam-leads' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $uid ); ?>-cond-valeur" name="<?php echo esc_attr( $base ); ?>[condition_value]" value="<?php echo esc_attr( $champ['condition_value'] ); ?>">
			</div>
		</div>

		<p class="iam-field__hint iam-champ-condition"><?php esc_html_e( 'Laissez les deux cases de condition vides pour un champ toujours visible. Un champ masqué n\'est jamais obligatoire.', 'iam-leads' ); ?></p>

		<div class="iam-champ-requis">
			<?php echo IAM_Leads_Admin_Menu::interrupteur( $base . '[required]', (bool) $champ['required'], __( 'Champ obligatoire', 'iam-leads' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
</div>
