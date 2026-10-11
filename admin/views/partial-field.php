<?php
/**
 * Bloc d'édition d'un champ.
 * Réutilisé pour les champs existants et pour le gabarit JavaScript.
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
?>
<div class="iam-champ" data-index="<?php echo esc_attr( $idx ); ?>">
	<div class="iam-champ-entete">
		<span class="iam-champ-poignee dashicons dashicons-menu-alt"></span>
		<strong class="iam-champ-titre"><?php echo esc_html( $champ['label'] ? $champ['label'] : __( 'Nouveau champ', 'iam-leads' ) ); ?></strong>
		<span class="iam-champ-type"><?php echo esc_html( IAM_Leads::types_champs()[ $champ['type'] ] ?? $champ['type'] ); ?></span>
		<span class="iam-champ-actions">
			<button type="button" class="button button-small iam-monter" title="<?php esc_attr_e( 'Monter', 'iam-leads' ); ?>">↑</button>
			<button type="button" class="button button-small iam-descendre" title="<?php esc_attr_e( 'Descendre', 'iam-leads' ); ?>">↓</button>
			<button type="button" class="button button-small iam-basculer"><?php esc_html_e( 'Ouvrir', 'iam-leads' ); ?></button>
			<button type="button" class="button button-small iam-danger iam-supprimer"><?php esc_html_e( 'Retirer', 'iam-leads' ); ?></button>
		</span>
	</div>

	<div class="iam-champ-corps" hidden>
		<div class="iam-grille-2">
			<p>
				<label><strong><?php esc_html_e( 'Type', 'iam-leads' ); ?></strong></label>
				<select name="<?php echo esc_attr( $base ); ?>[type]" class="widefat iam-champ-type-select">
					<?php foreach ( IAM_Leads::types_champs() as $cle => $libelle ) : ?>
						<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $champ['type'], $cle ); ?>>
							<?php echo esc_html( $libelle ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="iam-champ-largeur">
				<label><strong><?php esc_html_e( 'Largeur', 'iam-leads' ); ?></strong></label>
				<select name="<?php echo esc_attr( $base ); ?>[width]" class="widefat">
					<option value="full" <?php selected( $champ['width'], 'full' ); ?>><?php esc_html_e( 'Pleine largeur', 'iam-leads' ); ?></option>
					<option value="half" <?php selected( $champ['width'], 'half' ); ?>><?php esc_html_e( 'Demi-largeur', 'iam-leads' ); ?></option>
				</select>
			</p>

			<p>
				<label>
					<strong><?php esc_html_e( 'Étiquette', 'iam-leads' ); ?></strong>
					<span class="iam-oblig" aria-hidden="true">*</span>
				</label>
				<input type="text" class="widefat iam-champ-label" name="<?php echo esc_attr( $base ); ?>[label]"
					value="<?php echo esc_attr( $champ['label'] ); ?>">
				<span class="description"><?php esc_html_e( 'Obligatoire : un champ sans étiquette ne peut pas être enregistré.', 'iam-leads' ); ?></span>
			</p>

			<p class="iam-champ-identifiant">
				<label><strong><?php esc_html_e( 'Identifiant technique', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat iam-champ-name" name="<?php echo esc_attr( $base ); ?>[name]"
					value="<?php echo esc_attr( $champ['name'] ); ?>">
				<span class="description"><?php esc_html_e( 'Généré automatiquement depuis l\'étiquette.', 'iam-leads' ); ?></span>
			</p>

			<p class="iam-champ-placeholder">
				<label><strong><?php esc_html_e( 'Texte indicatif', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[placeholder]"
					value="<?php echo esc_attr( $champ['placeholder'] ); ?>">
			</p>

			<p class="iam-champ-mapping">
				<label><strong><?php esc_html_e( 'Correspondance CRM', 'iam-leads' ); ?></strong></label>
				<select name="<?php echo esc_attr( $base ); ?>[mapping]" class="widefat">
					<?php foreach ( IAM_Leads::mappings() as $cle => $libelle ) : ?>
						<option value="<?php echo esc_attr( $cle ); ?>" <?php selected( $champ['mapping'], $cle ); ?>>
							<?php echo esc_html( $libelle ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<span class="description"><?php esc_html_e( 'Détermine la colonne utilisée pour les filtres, le tableau de bord et le CRM.', 'iam-leads' ); ?></span>
			</p>
		</div>

		<p class="iam-champ-requis">
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( $champ['required'], 1 ); ?>>
				<?php esc_html_e( 'Champ obligatoire', 'iam-leads' ); ?>
			</label>
		</p>

		<p class="iam-champ-options">
			<label><strong><?php esc_html_e( 'Options', 'iam-leads' ); ?></strong></label>
			<textarea class="widefat" rows="5" name="<?php echo esc_attr( $base ); ?>[options]"><?php echo esc_textarea( implode( "\n", (array) $champ['options'] ) ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Une option par ligne. Utilisé pour les listes déroulantes, boutons radio et cases à cocher.', 'iam-leads' ); ?></span>
		</p>

		<div class="iam-grille-2 iam-champ-sous-labels">
			<p>
				<label><strong><?php esc_html_e( 'Libellé du prénom', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[label_prenom]"
					value="<?php echo esc_attr( $champ['label_prenom'] ); ?>"
					placeholder="<?php esc_attr_e( 'Prénom', 'iam-leads' ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'Libellé du nom de famille', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[label_nom]"
					value="<?php echo esc_attr( $champ['label_nom'] ); ?>"
					placeholder="<?php esc_attr_e( 'Nom de famille', 'iam-leads' ); ?>">
			</p>
		</div>

		<p class="iam-champ-description">
			<label><strong><?php esc_html_e( 'Texte d\'introduction de l\'étape', 'iam-leads' ); ?></strong></label>
			<textarea class="widefat" rows="3" name="<?php echo esc_attr( $base ); ?>[description]"><?php echo esc_textarea( $champ['description'] ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Facultatif. S\'affiche sous le titre de l\'étape. L\'étiquette du champ sert de titre.', 'iam-leads' ); ?></span>
		</p>

		<p class="iam-champ-valeur">
			<label><strong><?php esc_html_e( 'Valeur du champ caché', 'iam-leads' ); ?></strong></label>
			<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[valeur]"
				value="<?php echo esc_attr( $champ['valeur'] ); ?>">
		</p>

		<div class="iam-grille-2 iam-champ-condition">
			<p>
				<label><strong><?php esc_html_e( 'Afficher si le champ', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[condition_field]"
					value="<?php echo esc_attr( $champ['condition_field'] ); ?>"
					placeholder="<?php esc_attr_e( 'identifiant technique', 'iam-leads' ); ?>">
			</p>
			<p>
				<label><strong><?php esc_html_e( 'a pour valeur', 'iam-leads' ); ?></strong></label>
				<input type="text" class="widefat" name="<?php echo esc_attr( $base ); ?>[condition_value]"
					value="<?php echo esc_attr( $champ['condition_value'] ); ?>">
			</p>
		</div>
		<p class="description iam-champ-condition">
			<?php esc_html_e( 'Laissez ces deux cases vides pour un champ toujours visible. Un champ masqué n\'est jamais obligatoire.', 'iam-leads' ); ?>
		</p>
	</div>
</div>
