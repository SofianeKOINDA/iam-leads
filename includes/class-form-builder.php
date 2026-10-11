<?php
/**
 * Gestion des définitions de formulaires.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Form_Builder {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . IAM_LEADS_TABLE_FORMS;
	}

	/**
	 * Récupère tous les formulaires.
	 */
	public static function all() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY id ASC' ); // phpcs:ignore
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * Récupère un formulaire.
	 *
	 * @param int $id Identifiant.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Réglages d'affichage par défaut d'un formulaire.
	 */
	public static function default_reglages() {
		return array(
			'mode_affichage'       => 'page',
			'progression'          => 'barre',
			'bouton_precedent'     => 'Précédent',
			'bouton_suivant'       => 'Continuer',

			// Vides : le bouton garde les couleurs IAM par défaut (or, texte foncé).
			'couleur_bouton'       => '',
			'couleur_bouton_texte' => '',
		);
	}

	/**
	 * Valide une couleur hexadécimale (#abc ou #aabbcc).
	 * Une valeur non conforme rend une chaîne vide, qui fait retomber le
	 * bouton sur la couleur du thème plutôt que d'écrire un style cassé.
	 *
	 * @param mixed $valeur Couleur saisie.
	 * @return string
	 */
	public static function couleur( $valeur ) {
		$valeur = trim( (string) $valeur );

		return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $valeur ) ? $valeur : '';
	}

	/**
	 * Modes d'affichage disponibles.
	 */
	public static function modes_affichage() {
		return array(
			'page'   => __( 'Tout sur une page', 'iam-leads' ),
			'etapes' => __( 'Étape par étape', 'iam-leads' ),
		);
	}

	/**
	 * Types de progression disponibles.
	 */
	public static function types_progression() {
		return array(
			'barre'  => __( 'Barre de progression', 'iam-leads' ),
			'points' => __( 'Points numérotés', 'iam-leads' ),
			'aucune' => __( 'Aucune', 'iam-leads' ),
		);
	}

	/**
	 * Valeurs par défaut d'un champ.
	 * Appliquée à la lecture, elle garantit que les formulaires enregistrés
	 * avant l'ajout d'une clé restent exploitables sans migration de données.
	 */
	public static function default_champ() {
		return array(
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
	}

	/**
	 * Décode le JSON des champs et des réglages.
	 */
	private static function hydrate( $row ) {
		$champs = json_decode( (string) $row->champs, true );
		if ( ! is_array( $champs ) ) {
			$champs = array();
		}

		$defaut      = self::default_champ();
		$row->champs = array_map(
			static function ( $champ ) use ( $defaut ) {
				return wp_parse_args( (array) $champ, $defaut );
			},
			$champs
		);

		$reglages = json_decode( (string) ( $row->reglages ?? '' ), true );
		if ( ! is_array( $reglages ) ) {
			$reglages = array();
		}
		$row->reglages = wp_parse_args( $reglages, self::default_reglages() );

		return $row;
	}

	/**
	 * Regroupe les champs en étapes.
	 * Un séparateur ouvre une nouvelle étape ; les champs situés avant le
	 * premier séparateur forment une première étape sans titre.
	 *
	 * @param array $champs Champs du formulaire.
	 * @return array Liste d'étapes : titre, description, champs.
	 */
	public static function grouper_en_etapes( $champs ) {
		$etapes   = array();
		$courante = array(
			'titre'       => '',
			'description' => '',
			'champs'      => array(),
		);

		foreach ( $champs as $champ ) {
			if ( 'etape' === $champ['type'] ) {
				if ( $courante['champs'] ) {
					$etapes[] = $courante;
				}

				$courante = array(
					'titre'       => $champ['label'],
					'description' => $champ['description'],
					'champs'      => array(),
				);

				continue;
			}

			$courante['champs'][] = $champ;
		}

		if ( $courante['champs'] ) {
			$etapes[] = $courante;
		}

		return $etapes;
	}

	/**
	 * Enregistre (crée ou met à jour) un formulaire.
	 *
	 * @param array $data Données.
	 * @return int Identifiant du formulaire.
	 */
	public static function save( $data ) {
		global $wpdb;

		$recus  = isset( $data['champs'] ) && is_array( $data['champs'] ) ? $data['champs'] : array();
		$champs = self::sanitize_fields( $recus );

		/*
		 * Un champ écarté au nettoyage disparaissait sans laisser de trace :
		 * le formulaire était réenregistré amputé, et rien ne disait pourquoi.
		 * On consigne la définition du premier champ écarté.
		 */
		if ( count( $champs ) !== count( $recus ) ) {
			$ecarte = null;
			foreach ( $recus as $candidat ) {
				if ( ! self::sanitize_fields( array( $candidat ) ) ) {
					$ecarte = $candidat;
					break;
				}
			}

			IAM_Leads_Logger::ajouter(
				'formulaire',
				'echec',
				sprintf(
					'%1$d champ(s) envoyé(s), %2$d conservé(s) après nettoyage. Premier champ écarté : %3$s',
					count( $recus ),
					count( $champs ),
					wp_json_encode( $ecarte )
				)
			);
		}

		$reglages = isset( $data['reglages'] ) && is_array( $data['reglages'] ) ? $data['reglages'] : array();
		$reglages = array(
			'mode_affichage'       => array_key_exists( $reglages['mode_affichage'] ?? '', self::modes_affichage() ) ? $reglages['mode_affichage'] : 'page',
			'progression'          => array_key_exists( $reglages['progression'] ?? '', self::types_progression() ) ? $reglages['progression'] : 'barre',
			'bouton_precedent'     => sanitize_text_field( $reglages['bouton_precedent'] ?? 'Précédent' ),
			'bouton_suivant'       => sanitize_text_field( $reglages['bouton_suivant'] ?? 'Continuer' ),
			'couleur_bouton'       => self::couleur( $reglages['couleur_bouton'] ?? '' ),
			'couleur_bouton_texte' => self::couleur( $reglages['couleur_bouton_texte'] ?? '' ),
		);

		$payload = array(
			'nom'                  => sanitize_text_field( $data['nom'] ?? '' ),
			'champs'               => wp_json_encode( $champs ),
			'reglages'             => wp_json_encode( $reglages ),
			'bouton_texte'         => sanitize_text_field( $data['bouton_texte'] ?? 'Envoyer ma demande' ),
			'message_confirmation' => wp_kses_post( $data['message_confirmation'] ?? '' ),
			'plaquette_pdf'        => absint( $data['plaquette_pdf'] ?? 0 ),
			'programme'            => sanitize_text_field( $data['programme'] ?? '' ),
		);

		$id = absint( $data['id'] ?? 0 );

		/*
		 * L'écriture est vérifiée : une colonne absente ou un type refusé
		 * faisait auparavant échouer la requête sans un mot, et l'appelant
		 * était redirigé vers un formulaire vide comme si tout allait bien.
		 */
		if ( $id ) {
			$ecrit = $wpdb->update( self::table(), $payload, array( 'id' => $id ) ); // phpcs:ignore

			if ( false === $ecrit ) {
				IAM_Leads_Logger::ajouter(
					'formulaire',
					'echec',
					sprintf( 'Mise à jour du formulaire %1$d refusée par la base : %2$s', $id, $wpdb->last_error )
				);

				return 0;
			}

			return $id;
		}

		$payload['date_creation'] = current_time( 'mysql' );

		if ( false === $wpdb->insert( self::table(), $payload ) ) { // phpcs:ignore
			IAM_Leads_Logger::ajouter(
				'formulaire',
				'echec',
				sprintf( 'Création du formulaire refusée par la base : %s', $wpdb->last_error )
			);

			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Supprime un formulaire.
	 */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore
	}

	/**
	 * Nettoie la définition des champs reçue de l'admin.
	 *
	 * @param array $champs Champs bruts.
	 * @return array
	 */
	public static function sanitize_fields( $champs ) {
		$types  = array_keys( IAM_Leads::types_champs() );
		$maps   = array_keys( IAM_Leads::mappings() );
		$sortie = array();

		foreach ( $champs as $champ ) {
			$type = in_array( $champ['type'] ?? 'text', $types, true ) ? $champ['type'] : 'text';

			// Un séparateur d'étape n'a pas besoin d'étiquette pour être valide.
			if ( 'etape' !== $type && empty( $champ['label'] ) && empty( $champ['name'] ) ) {
				continue;
			}

			$name = sanitize_key( $champ['name'] ?? '' );

			if ( '' === $name ) {
				$name = sanitize_key( sanitize_title( $champ['label'] ?? '' ) );
			}

			if ( '' === $name && 'etape' === $type ) {
				$name = 'etape_' . ( count( $sortie ) + 1 );
			}

			$options = array();
			if ( ! empty( $champ['options'] ) ) {
				$brut    = is_array( $champ['options'] ) ? $champ['options'] : preg_split( '/\r\n|\r|\n/', (string) $champ['options'] );
				$options = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', (array) $brut ) ), 'strlen' ) );
			}

			$sortie[] = array(
				'type'            => $type,
				'label'           => sanitize_text_field( $champ['label'] ?? '' ),
				'name'            => $name,
				'placeholder'     => sanitize_text_field( $champ['placeholder'] ?? '' ),
				'required'        => ( ! empty( $champ['required'] ) && 'etape' !== $type ) ? 1 : 0,
				'options'         => $options,
				'width'           => ( isset( $champ['width'] ) && 'half' === $champ['width'] ) ? 'half' : 'full',
				'mapping'         => in_array( $champ['mapping'] ?? '', $maps, true ) ? $champ['mapping'] : '',
				'valeur'          => sanitize_text_field( $champ['valeur'] ?? '' ),
				'description'     => sanitize_textarea_field( $champ['description'] ?? '' ),
				'label_prenom'    => sanitize_text_field( $champ['label_prenom'] ?? '' ),
				'label_nom'       => sanitize_text_field( $champ['label_nom'] ?? '' ),
				'condition_field' => sanitize_key( $champ['condition_field'] ?? '' ),
				'condition_value' => sanitize_text_field( $champ['condition_value'] ?? '' ),
			);
		}

		return $sortie;
	}

	/**
	 * Liste des programmes proposés dans le formulaire de qualification.
	 * Les intitulés doivent rester alignés sur ceux publiés sur groupeiam.com.
	 */
	public static function programmes() {
		return array(
			'LPBA — Licence Professionnelle en Business Administration',
			'LPIG — Licence Professionnelle en Informatique de Gestion',
			'Licence en Droit des Affaires',
			'GBBA — Global Bachelor in Business Administration',
			'Master en Systèmes & Réseaux',
			'Master en Business Intelligence & Big Data',
			'Master en Génie Logiciel',
			'Master en Management de la Qualité',
			'Master Banque et Ingénierie financière',
			'Master en Management du Capital humain',
			'Master en Marketing et Intelligence d\'Affaires',
			'Master en Supply Chain Management',
			'MBA Finance',
			'MBA Management de Projets',
			'MBA Gestion des Ressources Humaines',
			'MBA Stratégies et Management des Organisations',
			'DBA — Doctorat en Business Administration',
			'BBA Pro — Bachelor en Administration des Affaires',
		);
	}

	/**
	 * Aligne les formulaires existants sur la liste ci-dessus.
	 * Les options d'un champ sont figées en base à la création du formulaire :
	 * sans cette synchronisation, une modification de programmes() ne serait
	 * visible que sur les formulaires créés ensuite. Seuls les champs reliés à
	 * la colonne « programme » sont touchés ; le reste de la définition, y
	 * compris l'étiquette et l'ordre des champs, est préservé.
	 *
	 * @return int Nombre de formulaires mis à jour.
	 */
	public static function sync_programme_options() {
		global $wpdb;

		$programmes = self::programmes();
		$a_choix    = array( 'select', 'radio', 'checkbox' );
		$modifies   = 0;

		foreach ( self::all() as $form ) {
			$champs  = $form->champs;
			$modifie = false;

			foreach ( $champs as $index => $champ ) {
				if ( 'programme' !== $champ['mapping'] || ! in_array( $champ['type'], $a_choix, true ) ) {
					continue;
				}

				if ( $champ['options'] === $programmes ) {
					continue;
				}

				$champs[ $index ]['options'] = $programmes;
				$modifie                     = true;
			}

			if ( ! $modifie ) {
				continue;
			}

			$wpdb->update( self::table(), array( 'champs' => wp_json_encode( $champs ) ), array( 'id' => $form->id ) ); // phpcs:ignore
			++$modifies;
		}

		return $modifies;
	}

	/**
	 * Options du champ « Comment avez-vous connu l'IAM ? ».
	 */
	public static function sources() {
		return array(
			'Réseaux sociaux',
			'Recherche Google',
			'Site internet de l\'IAM',
			'Recommandation d\'un étudiant ou ancien étudiant',
			'Recommandation d\'un proche',
			'Présentation de l\'IAM dans mon lycée ou établissement',
			'Visite du campus de l\'IAM',
			'Salon, forum ou événement',
			'WhatsApp',
			'E-mail ou SMS',
			'Télévision',
			'Radio',
			'Presse écrite ou média en ligne',
			'Affichage publicitaire',
			'Entreprise ou institution partenaire',
			'Autre',
		);
	}

	/**
	 * Crée le formulaire de qualification à l'activation, s'il n'existe pas déjà.
	 */
	public static function seed_default_form() {
		global $wpdb;

		$existe = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore
		if ( $existe > 0 ) {
			return;
		}

		$champs = array(
			array(
				'type'         => 'nom',
				'label'        => 'Votre nom',
				'name'         => 'identite',
				'required'     => 1,
				'width'        => 'full',
				'label_prenom' => 'Prénom',
				'label_nom'    => 'Nom de famille',
			),
			array(
				'type'     => 'text',
				'label'    => 'Pays de résidence',
				'name'     => 'pays_residence',
				'required' => 1,
				'width'    => 'half',
				'mapping'  => 'pays',
			),
			array(
				'type'        => 'tel',
				'label'       => 'Téléphone / WhatsApp',
				'name'        => 'telephone',
				'placeholder' => '+221 …',
				'required'    => 1,
				'width'       => 'half',
				'mapping'     => 'telephone',
			),
			array(
				'type'     => 'email',
				'label'    => 'Adresse e-mail',
				'name'     => 'email',
				'required' => 1,
				'width'    => 'full',
				'mapping'  => 'email',
			),
			array(
				'type'     => 'select',
				'label'    => 'Programme souhaité',
				'name'     => 'programme',
				'required' => 1,
				'width'    => 'half',
				'mapping'  => 'programme',
				'options'  => self::programmes(),
			),
			array(
				'type'     => 'select',
				'label'    => 'Niveau d\'études actuel ou dernier diplôme',
				'name'     => 'niveau_etudes',
				'required' => 1,
				'width'    => 'half',
				'mapping'  => 'niveau',
				'options'  => array(
					'Baccalauréat en cours',
					'Baccalauréat obtenu',
					'BEP/CAP',
					'Licence (Bac+3)',
					'Master (Bac+5)',
					'Doctorat',
					'Autre',
				),
			),
			array(
				'type'     => 'radio',
				'label'    => 'Moyen de contact préféré',
				'name'     => 'contact_prefere',
				'required' => 1,
				'width'    => 'full',
				'mapping'  => 'contact_prefere',
				'options'  => array( 'WhatsApp', 'Téléphone', 'E-mail' ),
			),
			array(
				'type'     => 'select',
				'label'    => 'Comment avez-vous connu l\'IAM ?',
				'name'     => 'source',
				'required' => 1,
				'width'    => 'full',
				'mapping'  => 'source',
				'options'  => self::sources(),
			),
			array(
				'type'            => 'select',
				'label'           => 'Précision réseau social',
				'name'            => 'reseau_social',
				'required'        => 0,
				'width'           => 'half',
				'mapping'         => 'reseau_social',
				'options'         => array( 'Facebook', 'Instagram', 'LinkedIn', 'TikTok', 'YouTube' ),
				'condition_field' => 'source',
				'condition_value' => 'Réseaux sociaux',
			),
			array(
				'type'            => 'text',
				'label'           => 'Autre source (précisez)',
				'name'            => 'autre_source',
				'required'        => 0,
				'width'           => 'half',
				'mapping'         => 'autre_source',
				'condition_field' => 'source',
				'condition_value' => 'Autre',
			),
			array(
				'type'     => 'acceptance',
				'label'    => 'J\'accepte que le Groupe IAM utilise ces informations pour traiter ma demande.',
				'name'     => 'consentement',
				'required' => 1,
				'width'    => 'full',
				'mapping'  => 'consentement',
			),
		);

		$wpdb->insert( // phpcs:ignore
			self::table(),
			array(
				'nom'                  => 'Demande d\'information — Groupe IAM',
				'champs'               => wp_json_encode( self::sanitize_fields( $champs ) ),
				'reglages'             => wp_json_encode( self::default_reglages() ),
				'bouton_texte'         => 'Envoyer ma demande',
				'message_confirmation' => 'Merci, votre demande est enregistrée. Le service des admissions vous recontacte sous 48 h.',
				'plaquette_pdf'        => 0,
				'programme'            => '',
				'date_creation'        => current_time( 'mysql' ),
			)
		);
	}
}
