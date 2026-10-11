<?php
/**
 * Export des leads : registre de colonnes, CSV et XLSX.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Export {

	const META_COLONNES = 'iam_leads_export_colonnes';
	const META_FORMAT   = 'iam_leads_export_format';
	const META_DETAIL   = 'iam_leads_export_detail';

	/**
	 * Lecture par lots. La version précédente chargeait 10 000 leads d'un bloc
	 * et tronquait silencieusement au-delà.
	 */
	const LOT = 500;

	public function __construct() {
		add_action( 'admin_post_iam_leads_export', array( $this, 'exporter' ) );
	}

	/* ---------------------------------------------------------------------
	 * Registre
	 * ------------------------------------------------------------------ */

	/**
	 * Colonnes fixes, communes à tous les formulaires.
	 *
	 * Les clés sont des identifiants techniques stables : elles servent au
	 * stockage de la préférence utilisateur et ne doivent jamais changer, même
	 * si le libellé évolue. L'ordre du tableau est l'ordre des colonnes dans le
	 * fichier produit.
	 *
	 * @return array
	 */
	public static function colonnes() {
		$statuts = IAM_Leads::statuts();
		$envois  = self::statuts_envoi();

		$simple = static function ( $propriete ) {
			return static function ( $lead ) use ( $propriete ) {
				return $lead->$propriete;
			};
		};

		$colonnes = array(
			'id'              => array(
				'label'   => __( 'ID', 'iam-leads' ),
				'largeur' => 6,
				'groupe'  => 'technique',
				'defaut'  => false,
				'valeur'  => $simple( 'id' ),
			),
			'date'            => array(
				'label'   => __( 'Date', 'iam-leads' ),
				'largeur' => 18,
				'groupe'  => 'demande',
				'defaut'  => true,
				'valeur'  => static function ( $lead ) {
					return mysql2date( 'd/m/Y H:i', $lead->date_creation );
				},
			),
			'nom_complet'     => array(
				'label'   => __( 'Nom complet', 'iam-leads' ),
				'largeur' => 24,
				'groupe'  => 'identite',
				'defaut'  => true,
				'valeur'  => $simple( 'nom_complet' ),
			),
			'email'           => array(
				'label'   => __( 'E-mail', 'iam-leads' ),
				'largeur' => 30,
				'groupe'  => 'identite',
				'defaut'  => true,
				'valeur'  => $simple( 'email' ),
			),
			'telephone'       => array(
				'label'   => __( 'Téléphone', 'iam-leads' ),
				'largeur' => 18,
				'groupe'  => 'identite',
				'defaut'  => true,
				'valeur'  => $simple( 'telephone' ),
			),
			'pays'            => array(
				'label'   => __( 'Pays', 'iam-leads' ),
				'largeur' => 16,
				'groupe'  => 'identite',
				'defaut'  => false,
				'valeur'  => $simple( 'pays' ),
			),
			'programme'       => array(
				'label'   => __( 'Programme', 'iam-leads' ),
				'largeur' => 45,
				'groupe'  => 'demande',
				'defaut'  => true,
				'valeur'  => $simple( 'programme' ),
			),
			'niveau'          => array(
				'label'   => __( 'Niveau d\'études', 'iam-leads' ),
				'largeur' => 26,
				'groupe'  => 'demande',
				'defaut'  => false,
				'valeur'  => $simple( 'niveau' ),
			),
			'contact_prefere' => array(
				'label'   => __( 'Contact préféré', 'iam-leads' ),
				'largeur' => 16,
				'groupe'  => 'demande',
				'defaut'  => true,
				'valeur'  => $simple( 'contact_prefere' ),
			),
			'source'          => array(
				'label'   => __( 'Source', 'iam-leads' ),
				'largeur' => 34,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'source' ),
			),
			'reseau_social'   => array(
				'label'   => __( 'Réseau social', 'iam-leads' ),
				'largeur' => 14,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'reseau_social' ),
			),
			'utm_source'      => array(
				'label'   => 'utm_source',
				'largeur' => 16,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'utm_source' ),
			),
			'utm_medium'      => array(
				'label'   => 'utm_medium',
				'largeur' => 16,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'utm_medium' ),
			),
			'utm_campaign'    => array(
				'label'   => 'utm_campaign',
				'largeur' => 20,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'utm_campaign' ),
			),
			'utm_content'     => array(
				'label'   => 'utm_content',
				'largeur' => 16,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'utm_content' ),
			),
			'utm_term'        => array(
				'label'   => 'utm_term',
				'largeur' => 16,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'utm_term' ),
			),
			'page_origine'    => array(
				'label'   => __( 'Page d\'origine', 'iam-leads' ),
				'largeur' => 40,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'page_origine' ),
			),
			'page_soumission' => array(
				'label'   => __( 'Page de soumission', 'iam-leads' ),
				'largeur' => 40,
				'groupe'  => 'provenance',
				'defaut'  => false,
				'valeur'  => $simple( 'page_soumission' ),
			),
			'ip'              => array(
				'label'   => __( 'Adresse IP', 'iam-leads' ),
				'largeur' => 16,
				'groupe'  => 'technique',
				'defaut'  => false,
				'note'    => __( 'Donnée personnelle — à n\'exporter qu\'en cas de besoin avéré.', 'iam-leads' ),
				'valeur'  => $simple( 'ip' ),
			),
			'user_agent'      => array(
				'label'   => __( 'Navigateur', 'iam-leads' ),
				'largeur' => 40,
				'groupe'  => 'technique',
				'defaut'  => false,
				'note'    => __( 'Donnée personnelle — à n\'exporter qu\'en cas de besoin avéré.', 'iam-leads' ),
				'valeur'  => $simple( 'user_agent' ),
			),
			'statut'          => array(
				'label'   => __( 'Statut', 'iam-leads' ),
				'largeur' => 14,
				'groupe'  => 'demande',
				'defaut'  => true,
				'valeur'  => static function ( $lead ) use ( $statuts ) {
					return $statuts[ $lead->statut ] ?? $lead->statut;
				},
			),
			'crm_status'      => array(
				'label'   => __( 'Envoi CRM', 'iam-leads' ),
				'largeur' => 14,
				'groupe'  => 'technique',
				'defaut'  => false,
				'valeur'  => static function ( $lead ) use ( $envois ) {
					return $envois[ $lead->crm_status ] ?? $lead->crm_status;
				},
			),
			'whatsapp_status' => array(
				'label'   => __( 'Envoi WhatsApp', 'iam-leads' ),
				'largeur' => 14,
				'groupe'  => 'technique',
				'defaut'  => false,
				'valeur'  => static function ( $lead ) use ( $envois ) {
					return $envois[ $lead->whatsapp_status ] ?? $lead->whatsapp_status;
				},
			),
			'reponses'        => array(
				'label'   => __( 'Réponses complètes', 'iam-leads' ),
				'largeur' => 60,
				'groupe'  => 'technique',
				'defaut'  => false,
				'valeur'  => static function ( $lead ) {
					$resume = array();

					foreach ( self::reponses( $lead ) as $champ ) {
						if ( '' !== (string) ( $champ['valeur'] ?? '' ) ) {
							$resume[] = $champ['label'] . ' : ' . $champ['valeur'];
						}
					}

					return implode( ' | ', $resume );
				},
			),
		);

		return apply_filters( 'iam_leads_export_colonnes', $colonnes );
	}

	/**
	 * Colonnes de détail d'un formulaire : une par champ sans mapping.
	 *
	 * Un champ mappé alimente déjà une colonne dédiée à l'enregistrement du
	 * lead (voir IAM_Leads_Lead_Manager::create). Le détailler ici ne ferait
	 * que dupliquer « E-mail » ou « Programme ». Seuls les champs non mappés
	 * n'existent nulle part ailleurs que dans le JSON `donnees`.
	 *
	 * @param int $form_id Formulaire filtré. 0 : aucune colonne de détail.
	 * @return array
	 */
	public static function colonnes_detail( $form_id ) {
		$form_id = (int) $form_id;
		$form    = $form_id ? IAM_Leads_Form_Builder::get( $form_id ) : null;

		if ( ! $form || ! is_array( $form->champs ) ) {
			return array();
		}

		$colonnes = array();

		foreach ( $form->champs as $champ ) {
			$type = (string) ( $champ['type'] ?? '' );
			$nom  = (string) ( $champ['name'] ?? '' );

			// Une étape ne porte aucune donnée ; un champ « Nom » est toujours
			// mappé via ses deux sous-champs.
			if ( '' === $nom || 'etape' === $type || 'nom' === $type ) {
				continue;
			}

			if ( ! empty( $champ['mapping'] ) ) {
				continue;
			}

			$colonnes[ 'champ:' . $nom ] = array(
				'label'   => '' !== (string) ( $champ['label'] ?? '' ) ? $champ['label'] : $nom,
				'largeur' => 30,
				'groupe'  => 'detail',
				'defaut'  => true,
				'valeur'  => static function ( $lead ) use ( $nom ) {
					$reponses = self::reponses( $lead );

					return $reponses[ $nom ]['valeur'] ?? '';
				},
			);
		}

		return apply_filters( 'iam_leads_export_colonnes_detail', $colonnes, $form_id );
	}

	/**
	 * Registre effectif d'un export.
	 *
	 * En mode détaillé, les colonnes de champs remplacent la colonne
	 * « Réponses complètes » : garder les deux reviendrait à exporter la même
	 * information deux fois.
	 *
	 * @param int  $form_id Formulaire filtré.
	 * @param bool $detail  Détailler les réponses.
	 * @return array
	 */
	public static function registre( $form_id = 0, $detail = false ) {
		$colonnes = self::colonnes();

		if ( ! $detail ) {
			return $colonnes;
		}

		$detaillees = self::colonnes_detail( $form_id );

		if ( ! $detaillees ) {
			return $colonnes;
		}

		unset( $colonnes['reponses'] );

		return array_merge( $colonnes, $detaillees );
	}

	/**
	 * Sections d'affichage du panneau d'export.
	 */
	public static function groupes() {
		return array(
			'identite'   => __( 'Identité', 'iam-leads' ),
			'demande'    => __( 'Demande', 'iam-leads' ),
			'provenance' => __( 'Provenance', 'iam-leads' ),
			'technique'  => __( 'Technique', 'iam-leads' ),
		);
	}

	/**
	 * Clés cochées pour un utilisateur qui n'a jamais touché aux réglages.
	 * Sept colonnes : le nécessaire d'une liste de relance téléphonique.
	 */
	public static function defaut() {
		$cles = array();

		foreach ( self::colonnes() as $cle => $colonne ) {
			if ( ! empty( $colonne['defaut'] ) ) {
				$cles[] = $cle;
			}
		}

		return $cles;
	}

	/**
	 * Libellés des statuts d'envoi CRM et WhatsApp.
	 */
	public static function statuts_envoi() {
		return array(
			'en_attente' => __( 'En attente', 'iam-leads' ),
			'envoye'     => __( 'Envoyé', 'iam-leads' ),
			'echec'      => __( 'Échec', 'iam-leads' ),
			'desactive'  => __( 'Désactivé', 'iam-leads' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Préférences
	 * ------------------------------------------------------------------ */

	/**
	 * Colonnes mémorisées, filtrées des clés disparues du registre.
	 */
	public static function preference_colonnes() {
		$memorise = get_user_meta( get_current_user_id(), self::META_COLONNES, true );

		if ( ! is_array( $memorise ) ) {
			return self::defaut();
		}

		return array_values( array_intersect( array_keys( self::colonnes() ), $memorise ) );
	}

	public static function preference_format() {
		$memorise = get_user_meta( get_current_user_id(), self::META_FORMAT, true );

		return 'xlsx' === $memorise ? 'xlsx' : 'csv';
	}

	public static function preference_detail() {
		return (bool) get_user_meta( get_current_user_id(), self::META_DETAIL, true );
	}

	/* ---------------------------------------------------------------------
	 * Traitement
	 * ------------------------------------------------------------------ */

	/**
	 * Génère et envoie le fichier.
	 */
	public function exporter() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'iam-leads' ) );
		}

		check_admin_referer( 'iam_leads_export' );

		$filtres = $this->filtres_recus();
		$detail  = ! empty( $_POST['detail'] ) && $filtres['form_id'];
		$format  = 'xlsx' === ( $_POST['format'] ?? '' ) ? 'xlsx' : 'csv';

		$registre = self::registre( $filtres['form_id'], $detail );
		$recues   = isset( $_POST['colonnes'] ) && is_array( $_POST['colonnes'] )
			? array_map( 'sanitize_text_field', wp_unslash( $_POST['colonnes'] ) )
			: array();

		// L'intersection dans ce sens impose l'ordre du registre, quel que soit
		// l'ordre d'arrivée des cases cochées.
		$choisies = array_intersect( array_keys( $registre ), $recues );

		if ( ! $choisies ) {
			$this->rediriger( 'export_vide' );
		}

		$colonnes = array();
		foreach ( $choisies as $cle ) {
			$colonnes[ $cle ] = $registre[ $cle ];
		}

		$this->memoriser( $choisies, $format, $detail );

		if ( 'xlsx' === $format && ! IAM_Leads_XLSX_Writer::disponible() ) {
			IAM_Leads_Logger::ajouter( 'export', 'echec', 'ZipArchive absent du serveur : export XLSX basculé en CSV.' );
			$format = 'csv';
		}

		if ( 'xlsx' === $format ) {
			$this->envoyer_xlsx( $colonnes, $filtres );
		}

		$this->envoyer_csv( $colonnes, $filtres );
	}

	/**
	 * Filtres de la liste, repris tels quels depuis le formulaire d'export.
	 */
	private function filtres_recus() {
		return array(
			'form_id'   => absint( $_POST['form_id'] ?? 0 ),
			'programme' => sanitize_text_field( wp_unslash( $_POST['programme'] ?? '' ) ),
			'statut'    => sanitize_key( $_POST['statut'] ?? '' ),
			'source'    => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
			'recherche' => sanitize_text_field( wp_unslash( $_POST['recherche'] ?? '' ) ),
			'du'        => sanitize_text_field( wp_unslash( $_POST['du'] ?? '' ) ),
			'au'        => sanitize_text_field( wp_unslash( $_POST['au'] ?? '' ) ),
		);
	}

	/**
	 * Enregistre la préférence de l'utilisateur courant.
	 *
	 * Les colonnes de détail sont propres à un formulaire : les mémoriser
	 * polluerait la sélection des autres formulaires. Seule la case
	 * « détailler » est retenue.
	 */
	private function memoriser( $choisies, $format, $detail ) {
		$utilisateur = get_current_user_id();
		$fixes       = array_values( array_intersect( array_keys( self::colonnes() ), $choisies ) );

		update_user_meta( $utilisateur, self::META_COLONNES, $fixes );
		update_user_meta( $utilisateur, self::META_FORMAT, $format );
		update_user_meta( $utilisateur, self::META_DETAIL, $detail ? 1 : 0 );
	}

	/**
	 * Parcourt les leads filtrés par lots.
	 *
	 * Le tri se fait sur l'identifiant : contrairement à la date, il est unique,
	 * donc l'ordre reste stable d'un lot à l'autre.
	 *
	 * @param array $filtres Filtres de la liste.
	 * @return Generator
	 */
	private function lots( $filtres ) {
		$page = 1;

		do {
			$resultat = IAM_Leads::instance()->leads->query(
				array_merge(
					$filtres,
					array(
						'par_page' => self::LOT,
						'page'     => $page,
						'orderby'  => 'id',
						'order'    => 'DESC',
					)
				)
			);

			foreach ( $resultat['items'] as $lead ) {
				yield $lead;
			}

			++$page;
		} while ( count( $resultat['items'] ) === self::LOT );
	}

	/* ---------------------------------------------------------------------
	 * Sorties
	 * ------------------------------------------------------------------ */

	private function envoyer_csv( $colonnes, $filtres ) {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . self::nom_fichier( 'csv' ) );

		$sortie = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// BOM UTF-8 : Excel ouvre correctement les accents.
		fwrite( $sortie, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

		self::ecrire_ligne( $sortie, array_values( wp_list_pluck( $colonnes, 'label' ) ) );

		foreach ( $this->lots( $filtres ) as $lead ) {
			$ligne = array();

			foreach ( $colonnes as $colonne ) {
				$ligne[] = self::neutraliser( self::valeur( $colonne, $lead ) );
			}

			self::ecrire_ligne( $sortie, $ligne );
		}

		fclose( $sortie );
		exit;
	}

	private function envoyer_xlsx( $colonnes, $filtres ) {
		$writer = new IAM_Leads_XLSX_Writer( $colonnes );

		foreach ( $this->lots( $filtres ) as $lead ) {
			$ligne = array();

			foreach ( $colonnes as $colonne ) {
				$ligne[] = self::valeur( $colonne, $lead );
			}

			$writer->ligne( $ligne );
		}

		$chemin = $writer->enregistrer();

		if ( ! $chemin ) {
			IAM_Leads_Logger::ajouter( 'export', 'echec', 'Écriture du fichier XLSX impossible : archive non créée.' );
			$this->rediriger( 'export_echec' );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename=' . self::nom_fichier( 'xlsx' ) );
		header( 'Content-Length: ' . filesize( $chemin ) );

		readfile( $chemin ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $chemin );
		exit;
	}

	/**
	 * Écrit une ligne CSV.
	 *
	 * L'échappement est explicitement vide : le caractère « \ » par défaut de
	 * PHP n'appartient pas au format CSV et corrompt les valeurs qui se
	 * terminent par un antislash. Le préciser évite en outre la dépréciation
	 * introduite en PHP 8.4.
	 */
	private static function ecrire_ligne( $sortie, $valeurs ) {
		fputcsv( $sortie, $valeurs, ';', '"', '' );
	}

	private static function nom_fichier( $extension ) {
		return 'iam-leads-' . current_time( 'Y-m-d-His' ) . '.' . $extension;
	}

	private function rediriger( $message ) {
		$retour = wp_get_referer();

		if ( ! $retour ) {
			$retour = admin_url( 'admin.php?page=iam-leads-list' );
		}

		wp_safe_redirect( add_query_arg( 'message', $message, $retour ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Valeurs
	 * ------------------------------------------------------------------ */

	private static function valeur( $colonne, $lead ) {
		$valeur = call_user_func( $colonne['valeur'], $lead );

		return is_scalar( $valeur ) ? (string) $valeur : '';
	}

	/**
	 * Réponses décodées d'un lead.
	 *
	 * Mémorisées sur le dernier lead lu : les colonnes d'une même ligne sont
	 * traitées à la suite, un seul json_decode par lead suffit donc.
	 */
	private static function reponses( $lead ) {
		static $dernier   = 0;
		static $decodees = array();

		if ( $dernier !== (int) $lead->id ) {
			$brut     = json_decode( (string) $lead->donnees, true );
			$decodees = is_array( $brut ) ? $brut : array();
			$dernier  = (int) $lead->id;
		}

		return $decodees;
	}

	/**
	 * Neutralise ce qu'Excel interpréterait comme une formule à l'ouverture
	 * d'un CSV : les champs viennent d'un formulaire public, une valeur
	 * commençant par « = » y est exécutée.
	 *
	 * Les numéros de téléphone commencent par « + » sans être des formules.
	 * Les préfixer rendrait « +221784820404 » illisible pour rien, donc une
	 * valeur qui ne contient que des chiffres et de la ponctuation de
	 * numérotation passe telle quelle.
	 */
	private static function neutraliser( $valeur ) {
		if ( '' === $valeur || ! in_array( $valeur[0], array( '=', '+', '-', '@' ), true ) ) {
			return $valeur;
		}

		if ( preg_match( '/^[+-][\d\s().\/-]*$/', $valeur ) ) {
			return $valeur;
		}

		return "'" . $valeur;
	}
}
