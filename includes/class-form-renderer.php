<?php
/**
 * Rendu public des formulaires et traitement des soumissions.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Form_Renderer {

	public function __construct() {
		add_shortcode( 'iam_form', array( $this, 'shortcode' ) );
		add_action( 'wp_ajax_iam_submit_form', array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_iam_submit_form', array( $this, 'handle_submit' ) );
	}

	/**
	 * Shortcode [iam_form id="X"].
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'iam_form' );
		$form = IAM_Leads_Form_Builder::get( $atts['id'] );

		if ( ! $form ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="iam-form-erreur">' . esc_html__( 'Formulaire introuvable : vérifiez l\'identifiant du shortcode.', 'iam-leads' ) . '</p>';
			}
			return '';
		}

		wp_enqueue_style( 'iam-leads' );
		wp_enqueue_script( 'iam-leads-forms' );

		return $this->render( $form );
	}

	/**
	 * Génère le HTML du formulaire avec la grille du thème Univet.
	 * Deux rendus possibles : tout sur une page, ou découpé en étapes
	 * dès qu'au moins un séparateur est présent.
	 */
	private function render( $form ) {
		$uid    = 'iam-form-' . (int) $form->id . '-' . wp_rand( 1000, 9999 );
		$etapes = array();

		if ( 'etapes' === $form->reglages['mode_affichage'] ) {
			$groupes = IAM_Leads_Form_Builder::grouper_en_etapes( $form->champs );

			// Sans séparateur, il n'y a qu'un groupe : on retombe sur le rendu simple.
			if ( count( $groupes ) > 1 ) {
				$etapes = $groupes;
			}
		}

		/*
		 * Les couleurs choisies sont posées en variables CSS sur l'enveloppe :
		 * la feuille de styles reste seule maîtresse de la mise en forme, et un
		 * formulaire sans réglage n'émet aucun style en ligne.
		 */
		$variables = array();

		if ( $form->reglages['couleur_bouton'] ) {
			$variables[] = '--iam-bouton:' . $form->reglages['couleur_bouton'];
		}

		if ( $form->reglages['couleur_bouton_texte'] ) {
			$variables[] = '--iam-bouton-texte:' . $form->reglages['couleur_bouton_texte'];
		}

		ob_start();
		?>
		<div class="iam-form-wrap" id="<?php echo esc_attr( $uid ); ?>"
			<?php echo $variables ? ' style="' . esc_attr( implode( ';', $variables ) ) . '"' : ''; ?>>
			<div class="iam-form-message" role="status" aria-live="polite" hidden></div>

			<form class="iam-form<?php echo $etapes ? ' iam-form-etapes' : ''; ?>" method="post" novalidate
				data-form-id="<?php echo esc_attr( $form->id ); ?>"
				data-ouverture="<?php echo esc_attr( time() ); ?>"
				data-etapes="<?php echo esc_attr( count( $etapes ) ); ?>">

				<?php if ( $etapes ) : ?>
					<?php $this->render_progression( $form, $etapes ); ?>

					<?php foreach ( $etapes as $index => $etape ) : ?>
						<fieldset class="iam-step" data-step="<?php echo esc_attr( $index ); ?>" <?php echo $index > 0 ? 'hidden' : ''; ?>>
							<?php if ( $etape['titre'] ) : ?>
								<legend class="iam-step-titre"><?php echo esc_html( $etape['titre'] ); ?></legend>
							<?php endif; ?>

							<?php if ( $etape['description'] ) : ?>
								<p class="iam-step-description"><?php echo esc_html( $etape['description'] ); ?></p>
							<?php endif; ?>

							<div class="rs-grid rs-grid-cols-2 rs-gap-20 rs-mob-p:grid-cols-1">
								<?php foreach ( $etape['champs'] as $champ ) : ?>
									<?php echo $this->render_field( $champ, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<?php endforeach; ?>
							</div>
						</fieldset>
					<?php endforeach; ?>

					<div class="iam-nav">
						<button type="button" class="iam-nav-precedent" hidden>
							<?php echo esc_html( $form->reglages['bouton_precedent'] ); ?>
						</button>
						<button type="button" class="iam-nav-suivant">
							<?php echo esc_html( $form->reglages['bouton_suivant'] ); ?>
						</button>
						<button type="submit" class="iam-form-submit" hidden>
							<?php echo esc_html( $form->bouton_texte ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="rs-grid rs-grid-cols-2 rs-gap-20 rs-mob-p:grid-cols-1">
						<?php
						$champs_visibles = array_filter(
							$form->champs,
							static function ( $champ ) {
								return 'etape' !== $champ['type'];
							}
						);
						?>
						<?php foreach ( $champs_visibles as $champ ) : ?>
							<?php echo $this->render_field( $champ, $uid ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php endforeach; ?>

						<div class="rs-col-span-2 rs-mob-p:col-span-1">
							<div class="form-btn-area">
								<button type="submit" class="iam-form-submit">
									<?php echo esc_html( $form->bouton_texte ); ?>
								</button>
							</div>
						</div>
					</div>
				<?php endif; ?>

				<div class="iam-hp-wrap" aria-hidden="true">
					<label>
						<?php esc_html_e( 'Ne remplissez pas ce champ', 'iam-leads' ); ?>
						<input type="text" name="iam_hp" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<input type="hidden" name="form_id" value="<?php echo esc_attr( $form->id ); ?>">
				<input type="hidden" name="page_soumission" value="">
				<input type="hidden" name="page_origine" value="">
				<?php foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ) as $utm ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $utm ); ?>" value="" data-iam-utm="<?php echo esc_attr( $utm ); ?>">
				<?php endforeach; ?>
			</form>

			<div class="iam-form-plaquette" hidden></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Indicateur de progression : barre ou points numérotés.
	 */
	private function render_progression( $form, $etapes ) {
		$type = $form->reglages['progression'];

		if ( 'aucune' === $type ) {
			return;
		}

		$total = count( $etapes );
		?>
		<div class="iam-progression iam-progression-<?php echo esc_attr( $type ); ?>" data-total="<?php echo esc_attr( $total ); ?>">
			<?php if ( 'points' === $type ) : ?>
				<ol class="iam-progression-points">
					<?php foreach ( $etapes as $index => $etape ) : ?>
						<li class="iam-point<?php echo 0 === $index ? ' is-actif' : ''; ?>"
							data-step="<?php echo esc_attr( $index ); ?>"
							<?php echo 0 === $index ? 'aria-current="step"' : ''; ?>>
							<span class="iam-point-numero"><?php echo esc_html( $index + 1 ); ?></span>
							<span class="iam-point-libelle">
								<?php
								echo esc_html(
									$etape['titre'] ? $etape['titre'] : sprintf(
										/* translators: %d : numéro de l'étape */
										__( 'Étape %d', 'iam-leads' ),
										$index + 1
									)
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<div class="iam-progression-barre">
					<div class="iam-progression-remplie" style="width:<?php echo esc_attr( round( 100 / $total, 2 ) ); ?>%"></div>
				</div>
			<?php endif; ?>

			<p class="iam-progression-texte">
				<?php
				printf(
					/* translators: 1 : étape courante, 2 : nombre total d'étapes */
					esc_html__( 'Étape %1$d sur %2$d', 'iam-leads' ),
					1,
					esc_html( $total )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Rendu d'un champ.
	 */
	private function render_field( $champ, $uid ) {
		$type     = $champ['type'];
		$name     = $champ['name'];
		$id       = $uid . '-' . $name;
		$requis   = ! empty( $champ['required'] );
		$classes  = 'iam-field iam-field-' . $type;
		$classes .= ( 'full' === $champ['width'] || in_array( $type, array( 'nom', 'textarea', 'acceptance', 'radio', 'checkbox' ), true ) )
			? ' rs-col-span-2 rs-mob-p:col-span-1'
			: '';

		if ( 'etape' === $type ) {
			return '';
		}

		if ( 'hidden' === $type ) {
			return sprintf(
				'<input type="hidden" name="%1$s" value="%2$s">',
				esc_attr( $name ),
				esc_attr( $champ['valeur'] )
			);
		}

		// Masqué dès le rendu : le script l'affiche si la condition est remplie,
		// ce qui évite un clignotement au chargement de la page.
		$conditionnel = '';
		if ( ! empty( $champ['condition_field'] ) ) {
			$conditionnel = sprintf(
				' data-condition-field="%1$s" data-condition-value="%2$s" hidden',
				esc_attr( $champ['condition_field'] ),
				esc_attr( $champ['condition_value'] )
			);
			$classes .= ' iam-field-conditionnel';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $conditionnel; // phpcs:ignore ?>>
			<?php if ( 'nom' === $type ) : ?>
				<span class="iam-label">
					<?php echo esc_html( $champ['label'] ); ?>
					<?php if ( $requis ) : ?><span class="iam-requis" aria-hidden="true">*</span><?php endif; ?>
				</span>
			<?php elseif ( 'acceptance' !== $type ) : ?>
				<label class="iam-label" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $champ['label'] ); ?>
					<?php if ( $requis ) : ?><span class="iam-requis" aria-hidden="true">*</span><?php endif; ?>
				</label>
			<?php endif; ?>

			<?php
			switch ( $type ) {
				case 'nom':
					$sous = array(
						'prenom' => $champ['label_prenom'] ? $champ['label_prenom'] : __( 'Prénom', 'iam-leads' ),
						'nom'    => $champ['label_nom'] ? $champ['label_nom'] : __( 'Nom de famille', 'iam-leads' ),
					);

					echo '<div class="iam-sous-champs">';

					foreach ( $sous as $cle => $libelle ) {
						$sous_id   = $id . '-' . $cle;
						$sous_name = $name . '_' . $cle;

						printf(
							'<div class="iam-sous-champ"><label class="iam-sous-label" for="%1$s">%2$s</label>' .
							'<input type="text" id="%1$s" name="%3$s" autocomplete="%4$s"%5$s>' .
							'<span class="iam-field-erreur" data-pour="%3$s" hidden></span></div>',
							esc_attr( $sous_id ),
							esc_html( $libelle ),
							esc_attr( $sous_name ),
							'prenom' === $cle ? 'given-name' : 'family-name',
							$requis ? ' data-required="1"' : ''
						);
					}

					echo '</div>';
					break;

				case 'textarea':
					printf(
						'<textarea id="%1$s" name="%2$s" rows="5" placeholder="%3$s"%4$s></textarea>',
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $champ['placeholder'] ),
						$requis ? ' data-required="1"' : ''
					);
					break;

				case 'select':
					printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $requis ? ' data-required="1"' : '' );
					printf( '<option value="">%s</option>', esc_html__( 'Choisir…', 'iam-leads' ) );
					foreach ( $champ['options'] as $option ) {
						printf( '<option value="%1$s">%1$s</option>', esc_attr( $option ) );
					}
					echo '</select>';
					break;

				case 'radio':
				case 'checkbox':
					$input_type = 'radio' === $type ? 'radio' : 'checkbox';
					$suffixe    = 'checkbox' === $type ? '[]' : '';
					echo '<div class="iam-choix" role="group"' . ( $requis ? ' data-required="1"' : '' ) . '>';
					foreach ( $champ['options'] as $i => $option ) {
						printf(
							'<label class="iam-choix-item"><input type="%1$s" name="%2$s%3$s" value="%4$s" id="%5$s-%6$d"><span>%4$s</span></label>',
							esc_attr( $input_type ),
							esc_attr( $name ),
							esc_attr( $suffixe ),
							esc_attr( $option ),
							esc_attr( $id ),
							(int) $i
						);
					}
					echo '</div>';
					break;

				case 'acceptance':
					printf(
						'<label class="iam-choix-item iam-acceptance"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s><span>%4$s%5$s</span></label>',
						esc_attr( $id ),
						esc_attr( $name ),
						$requis ? ' data-required="1"' : '',
						esc_html( $champ['label'] ),
						$this->lien_confidentialite()
					);
					break;

				case 'date':
					printf(
						'<input type="date" id="%1$s" name="%2$s"%3$s>',
						esc_attr( $id ),
						esc_attr( $name ),
						$requis ? ' data-required="1"' : ''
					);
					break;

				default:
					printf(
						'<input type="%1$s" id="%2$s" name="%3$s" placeholder="%4$s" autocomplete="%5$s"%6$s>',
						esc_attr( in_array( $type, array( 'email', 'tel' ), true ) ? $type : 'text' ),
						esc_attr( $id ),
						esc_attr( $name ),
						esc_attr( $champ['placeholder'] ),
						esc_attr( $this->autocomplete( $champ ) ),
						$requis ? ' data-required="1"' : ''
					);
			}
			?>
			<span class="iam-field-erreur" hidden></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Valeur autocomplete adaptée au mapping du champ.
	 */
	private function autocomplete( $champ ) {
		$map = array(
			'prenom'    => 'given-name',
			'nom'       => 'family-name',
			'email'     => 'email',
			'telephone' => 'tel',
			'pays'      => 'country-name',
		);
		return $map[ $champ['mapping'] ] ?? 'off';
	}

	/**
	 * Lien vers la politique de confidentialité, si renseignée dans les réglages.
	 */
	private function lien_confidentialite() {
		$url = IAM_Leads::get_setting( 'privacy_url' );
		if ( ! $url ) {
			return '';
		}
		return sprintf(
			' <a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
			esc_url( $url ),
			esc_html__( 'Politique de confidentialité', 'iam-leads' )
		);
	}

	/**
	 * Traitement AJAX d'une soumission.
	 */
	public function handle_submit() {
		check_ajax_referer( 'iam_leads_submit', 'nonce' );

		$form_id = absint( $_POST['form_id'] ?? 0 );
		$form    = IAM_Leads_Form_Builder::get( $form_id );

		if ( ! $form ) {
			wp_send_json_error( array( 'message' => __( 'Ce formulaire n\'existe plus.', 'iam-leads' ) ), 404 );
		}

		// Pot de miel : un robot remplit le champ, un humain ne le voit pas.
		if ( ! empty( $_POST['iam_hp'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Envoi refusé.', 'iam-leads' ) ), 400 );
		}

		// Délai minimal entre affichage et envoi.
		$ouverture = absint( $_POST['ouverture'] ?? 0 );
		$delai     = (int) IAM_Leads::get_setting( 'delai_anti_spam', 3 );
		if ( $ouverture && $delai > 0 && ( time() - $ouverture ) < $delai ) {
			wp_send_json_error( array( 'message' => __( 'Envoi trop rapide. Réessayez.', 'iam-leads' ) ), 400 );
		}

		$valeurs = array();
		$erreurs = array();

		foreach ( $form->champs as $champ ) {
			$name = $champ['name'];

			// Un séparateur ne porte aucune donnée.
			if ( 'etape' === $champ['type'] ) {
				continue;
			}

			// Un champ masqué par la logique conditionnelle n'est jamais obligatoire.
			$actif = true;
			if ( ! empty( $champ['condition_field'] ) ) {
				$declencheur = sanitize_text_field( wp_unslash( $_POST[ $champ['condition_field'] ] ?? '' ) );
				$actif       = ( $declencheur === $champ['condition_value'] );
			}

			// Le champ « Nom » produit deux valeurs, mappées séparément.
			if ( 'nom' === $champ['type'] ) {
				$sous = array(
					'prenom' => array(
						'label'   => $champ['label_prenom'] ? $champ['label_prenom'] : __( 'Prénom', 'iam-leads' ),
						'mapping' => 'prenom',
					),
					'nom'    => array(
						'label'   => $champ['label_nom'] ? $champ['label_nom'] : __( 'Nom de famille', 'iam-leads' ),
						'mapping' => 'nom',
					),
				);

				foreach ( $sous as $cle => $meta ) {
					$sous_name = $name . '_' . $cle;
					$valeur    = sanitize_text_field( wp_unslash( $_POST[ $sous_name ] ?? '' ) );

					if ( $actif && ! empty( $champ['required'] ) && '' === $valeur ) {
						$erreurs[ $sous_name ] = __( 'Ce champ est obligatoire.', 'iam-leads' );
					}

					$valeurs[ $sous_name ] = array(
						'label'   => $meta['label'],
						'valeur'  => $valeur,
						'mapping' => $meta['mapping'],
					);
				}

				continue;
			}

			$brut = $_POST[ $name ] ?? ( 'checkbox' === $champ['type'] ? array() : '' );

			if ( 'checkbox' === $champ['type'] ) {
				$valeur = implode( ', ', array_map( 'sanitize_text_field', (array) $brut ) );
			} elseif ( 'textarea' === $champ['type'] ) {
				$valeur = sanitize_textarea_field( wp_unslash( $brut ) );
			} elseif ( 'email' === $champ['type'] ) {
				$valeur = sanitize_email( wp_unslash( $brut ) );
			} else {
				$valeur = sanitize_text_field( wp_unslash( $brut ) );
			}

			if ( $actif && ! empty( $champ['required'] ) && '' === $valeur ) {
				$erreurs[ $name ] = __( 'Ce champ est obligatoire.', 'iam-leads' );
			}

			if ( 'email' === $champ['type'] && '' !== $valeur && ! is_email( $valeur ) ) {
				$erreurs[ $name ] = __( 'Saisissez une adresse e-mail valide.', 'iam-leads' );
			}

			$valeurs[ $name ] = array(
				'label'   => $champ['label'],
				'valeur'  => $valeur,
				'mapping' => $champ['mapping'],
			);
		}

		if ( $erreurs ) {
			wp_send_json_error(
				array(
					'message' => __( 'Certains champs doivent être corrigés.', 'iam-leads' ),
					'champs'  => $erreurs,
				),
				422
			);
		}

		$lead_id = IAM_Leads::instance()->leads->create( $form, $valeurs );

		if ( ! $lead_id ) {
			wp_send_json_error( array( 'message' => __( 'L\'enregistrement a échoué. Réessayez ou contactez-nous au +221 33 869 36 36.', 'iam-leads' ) ), 500 );
		}

		$lead = IAM_Leads::instance()->leads->get( $lead_id );

		IAM_Leads_Emails::notifier_admin( $lead, $form );
		IAM_Leads_Emails::confirmer_visiteur( $lead, $form );
		IAM_Leads_CRM_Integration::envoyer( $lead );
		IAM_Leads_WhatsApp::notifier( $lead );

		wp_send_json_success(
			array(
				'message'   => $form->message_confirmation ? $form->message_confirmation : __( 'Merci, votre demande est enregistrée.', 'iam-leads' ),
				'plaquette' => $this->plaquette_pour( $lead, $form ),
			)
		);
	}

	/**
	 * Retourne la plaquette correspondant au programme choisi.
	 */
	private function plaquette_pour( $lead, $form ) {
		$plaquettes = (array) IAM_Leads::get_setting( 'plaquettes', array() );
		$programme  = $lead->programme;
		$piece_id   = 0;

		if ( $programme && ! empty( $plaquettes[ $programme ] ) ) {
			$piece_id = absint( $plaquettes[ $programme ] );
		} elseif ( ! empty( $form->plaquette_pdf ) ) {
			$piece_id = absint( $form->plaquette_pdf );
		}

		if ( ! $piece_id ) {
			return array(
				'disponible' => false,
				'message'    => __( 'Notre équipe vous contactera sous 48h.', 'iam-leads' ),
			);
		}

		$url = wp_get_attachment_url( $piece_id );

		if ( ! $url ) {
			return array(
				'disponible' => false,
				'message'    => __( 'Notre équipe vous contactera sous 48h.', 'iam-leads' ),
			);
		}

		return array(
			'disponible' => true,
			'url'        => $url,
			'libelle'    => sprintf(
				/* translators: %s : nom du programme */
				__( 'Télécharger la plaquette — %s', 'iam-leads' ),
				$programme ? $programme : $form->programme
			),
		);
	}
}
