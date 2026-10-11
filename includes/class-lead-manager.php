<?php
/**
 * Stockage et gestion des leads.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Lead_Manager {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . IAM_LEADS_TABLE_LEADS;
	}

	/**
	 * Enregistre une soumission.
	 *
	 * @param object $form    Formulaire.
	 * @param array  $valeurs Valeurs nettoyées, indexées par nom de champ.
	 * @return int Identifiant du lead créé, 0 en cas d'échec.
	 */
	public function create( $form, $valeurs ) {
		global $wpdb;

		$mappe = array();
		foreach ( $valeurs as $champ ) {
			if ( ! empty( $champ['mapping'] ) ) {
				$mappe[ $champ['mapping'] ] = $champ['valeur'];
			}
		}

		$nom_complet = trim( ( $mappe['prenom'] ?? '' ) . ' ' . ( $mappe['nom'] ?? '' ) );

		// « Autre » et « Réseaux sociaux » : on garde la précision saisie comme source lisible.
		$source = $mappe['source'] ?? '';
		if ( 'Autre' === $source && ! empty( $mappe['autre_source'] ) ) {
			$source = $mappe['autre_source'];
		}

		$utm = IAM_Leads_UTM_Capture::depuis_requete();

		$donnees = array(
			'form_id'         => (int) $form->id,
			'donnees'         => wp_json_encode( $valeurs, JSON_UNESCAPED_UNICODE ),
			'nom_complet'     => $nom_complet,
			'email'           => $mappe['email'] ?? '',
			'telephone'       => $mappe['telephone'] ?? '',
			'pays'            => $mappe['pays'] ?? '',
			'programme'       => $mappe['programme'] ?? $form->programme,
			'niveau'          => $mappe['niveau'] ?? '',
			'contact_prefere' => $mappe['contact_prefere'] ?? '',
			'source'          => $source,
			'reseau_social'   => $mappe['reseau_social'] ?? '',
			'utm_source'      => $utm['utm_source'],
			'utm_medium'      => $utm['utm_medium'],
			'utm_campaign'    => $utm['utm_campaign'],
			'utm_content'     => $utm['utm_content'],
			'utm_term'        => $utm['utm_term'],
			'page_origine'    => $utm['page_origine'],
			'page_soumission' => $utm['page_soumission'],
			'ip'              => IAM_Leads_UTM_Capture::ip_visiteur(),
			'user_agent'      => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ),
			'statut'          => 'nouveau',
			'crm_status'      => 'en_attente',
			'whatsapp_status' => 'en_attente',
			'date_creation'   => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( self::table(), $donnees ); // phpcs:ignore

		if ( $ok ) {
			IAM_Leads_Dashboard::purger_cache();
		}

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Récupère un lead.
	 */
	public function get( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ); // phpcs:ignore

		if ( $row ) {
			$row->donnees_decodees = json_decode( (string) $row->donnees, true );
			if ( ! is_array( $row->donnees_decodees ) ) {
				$row->donnees_decodees = array();
			}
		}

		return $row;
	}

	/**
	 * Recherche filtrée.
	 *
	 * @param array $args Filtres.
	 * @return array{items:array,total:int}
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'form_id'   => 0,
				'programme' => '',
				'statut'    => '',
				'source'    => '',
				'recherche' => '',
				'du'        => '',
				'au'        => '',
				'par_page'  => 25,
				'page'      => 1,
				'orderby'   => 'date_creation',
				'order'     => 'DESC',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['form_id'] ) {
			$where[]  = 'form_id = %d';
			$params[] = (int) $args['form_id'];
		}
		if ( $args['programme'] ) {
			$where[]  = 'programme = %s';
			$params[] = $args['programme'];
		}
		if ( $args['statut'] ) {
			$where[]  = 'statut = %s';
			$params[] = $args['statut'];
		}
		if ( $args['source'] ) {
			$where[]  = 'source = %s';
			$params[] = $args['source'];
		}
		if ( $args['du'] ) {
			$where[]  = 'date_creation >= %s';
			$params[] = $args['du'] . ' 00:00:00';
		}
		if ( $args['au'] ) {
			$where[]  = 'date_creation <= %s';
			$params[] = $args['au'] . ' 23:59:59';
		}
		if ( $args['recherche'] ) {
			$like     = '%' . $wpdb->esc_like( $args['recherche'] ) . '%';
			$where[]  = '(nom_complet LIKE %s OR email LIKE %s OR telephone LIKE %s OR donnees LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$clause    = implode( ' AND ', $where );
		$colonnes  = array( 'id', 'date_creation', 'nom_complet', 'programme', 'source', 'statut' );
		$orderby   = in_array( $args['orderby'], $colonnes, true ) ? $args['orderby'] : 'date_creation';
		$order     = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$par_page  = max( 1, (int) $args['par_page'] );
		$offset    = ( max( 1, (int) $args['page'] ) - 1 ) * $par_page;

		$sql_total = "SELECT COUNT(*) FROM " . self::table() . " WHERE {$clause}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql_total, $params ) ) : $wpdb->get_var( $sql_total ) ); // phpcs:ignore

		$sql   = "SELECT * FROM " . self::table() . " WHERE {$clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$args2 = array_merge( $params, array( $par_page, $offset ) );
		$items = $wpdb->get_results( $wpdb->prepare( $sql, $args2 ) ); // phpcs:ignore

		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Met à jour le statut d'un lead.
	 */
	public function update_statut( $id, $statut ) {
		global $wpdb;

		if ( ! array_key_exists( $statut, IAM_Leads::statuts() ) ) {
			return false;
		}

		$ok = $wpdb->update( self::table(), array( 'statut' => $statut ), array( 'id' => (int) $id ) ); // phpcs:ignore

		if ( false !== $ok ) {
			IAM_Leads_Dashboard::purger_cache();
		}

		return $ok;
	}

	/**
	 * Met à jour un statut d'envoi (CRM ou WhatsApp).
	 */
	public function update_envoi( $id, $colonne, $valeur ) {
		global $wpdb;

		if ( ! in_array( $colonne, array( 'crm_status', 'whatsapp_status' ), true ) ) {
			return false;
		}

		return $wpdb->update( self::table(), array( $colonne => $valeur ), array( 'id' => (int) $id ) ); // phpcs:ignore
	}

	/**
	 * Supprime un lead.
	 */
	public function delete( $id ) {
		global $wpdb;

		$ok = $wpdb->delete( self::table(), array( 'id' => (int) $id ) ); // phpcs:ignore

		if ( $ok ) {
			IAM_Leads_Dashboard::purger_cache();
		}

		return $ok;
	}

	/**
	 * Valeurs distinctes d'une colonne, pour alimenter les filtres.
	 */
	public function valeurs_distinctes( $colonne ) {
		global $wpdb;

		if ( ! in_array( $colonne, array( 'programme', 'source', 'pays' ), true ) ) {
			return array();
		}

		$sql = "SELECT DISTINCT {$colonne} FROM " . self::table() . " WHERE {$colonne} <> '' ORDER BY {$colonne} ASC";

		return (array) $wpdb->get_col( $sql ); // phpcs:ignore
	}
}
