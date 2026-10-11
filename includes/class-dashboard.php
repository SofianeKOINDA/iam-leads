<?php
/**
 * Données du tableau de bord.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Dashboard {

	/**
	 * Durée de vie du cache d'un payload, en secondes.
	 */
	const TTL_CACHE = 300;

	/**
	 * Préfixe des transients, pour pouvoir tous les purger d'un coup.
	 */
	const PREFIXE_CACHE = 'iam_dash_';

	/**
	 * Traduit une période en bornes de dates.
	 *
	 * @param string $periode 7j, 30j, 3m, 1a, perso.
	 * @param string $du      Date début (Y-m-d) pour la période personnalisée.
	 * @param string $au      Date fin (Y-m-d).
	 * @return array{du:string,au:string,granularite:string}
	 */
	public function bornes( $periode = '30j', $du = '', $au = '' ) {
		$au_defaut = current_time( 'Y-m-d' );

		switch ( $periode ) {
			case '7j':
				return array( 'du' => gmdate( 'Y-m-d', strtotime( '-6 days', current_time( 'timestamp' ) ) ), 'au' => $au_defaut, 'granularite' => 'jour' );
			case '3m':
				return array( 'du' => gmdate( 'Y-m-d', strtotime( '-3 months', current_time( 'timestamp' ) ) ), 'au' => $au_defaut, 'granularite' => 'semaine' );
			case '1a':
				return array( 'du' => gmdate( 'Y-m-d', strtotime( '-1 year', current_time( 'timestamp' ) ) ), 'au' => $au_defaut, 'granularite' => 'mois' );
			case 'perso':
				$du = $du ? $du : gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) );
				$au = $au ? $au : $au_defaut;
				$ecart = ( strtotime( $au ) - strtotime( $du ) ) / DAY_IN_SECONDS;
				return array( 'du' => $du, 'au' => $au, 'granularite' => $ecart > 180 ? 'mois' : ( $ecart > 60 ? 'semaine' : 'jour' ) );
			case '30j':
			default:
				return array( 'du' => gmdate( 'Y-m-d', strtotime( '-29 days', current_time( 'timestamp' ) ) ), 'au' => $au_defaut, 'granularite' => 'jour' );
		}
	}

	/**
	 * Période immédiatement antérieure, de même durée, pour les comparaisons.
	 *
	 * @param string $du Début de la période courante (Y-m-d).
	 * @param string $au Fin de la période courante (Y-m-d).
	 * @return array{du:string,au:string}
	 */
	public function bornes_precedentes( $du, $au ) {
		$jours = max( 1, (int) round( ( strtotime( $au ) - strtotime( $du ) ) / DAY_IN_SECONDS ) + 1 );

		return array(
			'du' => gmdate( 'Y-m-d', strtotime( '-' . $jours . ' days', strtotime( $du ) ) ),
			'au' => gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $du ) ) ),
		);
	}

	/**
	 * Cartes de statistiques, avec comparaison à la période précédente.
	 *
	 * Deux requêtes agrégées au lieu de quatre COUNT séparés : les compteurs
	 * qui partagent une plage de dates sont calculés dans le même passage.
	 */
	public function cartes( $du, $au ) {
		global $wpdb;

		$t    = IAM_Leads_Lead_Manager::table();
		$prec = $this->bornes_precedentes( $du, $au );

		// Période courante + période précédente : un seul balayage sur la plage élargie.
		$plage = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM( date_creation >= %s ) AS total,
					SUM( date_creation >= %s AND statut = 'converti' ) AS convertis,
					SUM( date_creation < %s ) AS total_prec,
					SUM( date_creation < %s AND statut = 'converti' ) AS convertis_prec
				 FROM {$t}
				 WHERE date_creation BETWEEN %s AND %s",
				$du . ' 00:00:00',
				$du . ' 00:00:00',
				$du . ' 00:00:00',
				$du . ' 00:00:00',
				$prec['du'] . ' 00:00:00',
				$au . ' 23:59:59'
			)
		); // phpcs:ignore

		// Aujourd'hui, hier et le mois courant : un seul balayage depuis le 1er du mois.
		$debut_mois = current_time( 'Y-m-01' );
		$aujourdhui = current_time( 'Y-m-d' );
		$hier       = gmdate( 'Y-m-d', strtotime( '-1 day', current_time( 'timestamp' ) ) );
		$depuis     = min( $debut_mois, $hier );

		$recent = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM( DATE( date_creation ) = %s ) AS aujourdhui,
					SUM( DATE( date_creation ) = %s ) AS hier,
					SUM( date_creation >= %s ) AS ce_mois
				 FROM {$t}
				 WHERE date_creation >= %s",
				$aujourdhui,
				$hier,
				$debut_mois . ' 00:00:00',
				$depuis . ' 00:00:00'
			)
		); // phpcs:ignore

		// Mois précédent, pour le delta de la tuile « ce mois-ci ».
		$mois_prec_debut = gmdate( 'Y-m-01', strtotime( '-1 month', current_time( 'timestamp' ) ) );
		$mois_prec_fin   = gmdate( 'Y-m-t', strtotime( '-1 month', current_time( 'timestamp' ) ) );

		$mois_prec = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$t} WHERE date_creation BETWEEN %s AND %s",
				$mois_prec_debut . ' 00:00:00',
				$mois_prec_fin . ' 23:59:59'
			)
		); // phpcs:ignore

		$total          = (int) ( $plage->total ?? 0 );
		$total_prec     = (int) ( $plage->total_prec ?? 0 );
		$convertis      = (int) ( $plage->convertis ?? 0 );
		$convertis_prec = (int) ( $plage->convertis_prec ?? 0 );

		$conversion      = $total > 0 ? round( ( $convertis / $total ) * 100, 1 ) : 0;
		$conversion_prec = $total_prec > 0 ? round( ( $convertis_prec / $total_prec ) * 100, 1 ) : 0;

		return array(
			'total'            => $total,
			'total_prec'       => $total_prec,
			'total_evol'       => $this->evolution( $total, $total_prec ),
			'aujourdhui'       => (int) ( $recent->aujourdhui ?? 0 ),
			'hier'             => (int) ( $recent->hier ?? 0 ),
			'ce_mois'          => (int) ( $recent->ce_mois ?? 0 ),
			'mois_prec'        => $mois_prec,
			'ce_mois_evol'     => $this->evolution( (int) ( $recent->ce_mois ?? 0 ), $mois_prec ),
			'conversion'       => $conversion,
			'conversion_prec'  => $conversion_prec,
			'conversion_ecart' => round( $conversion - $conversion_prec, 1 ),
			'bornes_prec'      => $prec,
		);
	}

	/**
	 * Variation en pourcentage entre deux valeurs.
	 *
	 * @return float|null Null quand la référence est nulle : « +∞ % » n'a pas de sens.
	 */
	private function evolution( $courant, $precedent ) {
		if ( $precedent <= 0 ) {
			return null;
		}

		return round( ( ( $courant - $precedent ) / $precedent ) * 100, 1 );
	}

	/**
	 * Série temporelle des soumissions.
	 *
	 * Les jours sans lead sont comblés côté PHP : sans cela la courbe relie
	 * deux dates éloignées par un segment droit qui invente des valeurs.
	 */
	public function serie_temporelle( $du, $au, $granularite = 'jour' ) {
		global $wpdb;
		$t = IAM_Leads_Lead_Manager::table();

		switch ( $granularite ) {
			case 'mois':
				$format = '%%Y-%%m';
				break;
			case 'semaine':
				$format = '%%x-S%%v';
				break;
			default:
				$format = '%%Y-%%m-%%d';
		}

		$sql = $wpdb->prepare(
			"SELECT DATE_FORMAT(date_creation, '{$format}') AS periode, COUNT(*) AS total
			 FROM {$t}
			 WHERE date_creation BETWEEN %s AND %s
			 GROUP BY periode ORDER BY periode ASC",
			$du . ' 00:00:00',
			$au . ' 23:59:59'
		);

		$rows = (array) $wpdb->get_results( $sql ); // phpcs:ignore

		$par_cle = array();
		foreach ( $rows as $row ) {
			$par_cle[ $row->periode ] = (int) $row->total;
		}

		$labels  = array();
		$valeurs = array();

		if ( 'jour' === $granularite ) {
			$curseur = strtotime( $du );
			$fin     = strtotime( $au );

			while ( $curseur <= $fin ) {
				$cle = gmdate( 'Y-m-d', $curseur );

				$labels[]  = $cle;
				$valeurs[] = $par_cle[ $cle ] ?? 0;

				$curseur = strtotime( '+1 day', $curseur );
			}
		} else {
			$labels  = array_keys( $par_cle );
			$valeurs = array_values( $par_cle );
		}

		return array(
			'labels'  => $labels,
			'valeurs' => $valeurs,
		);
	}

	/**
	 * Comptes journaliers des N derniers jours (sparkline de la tuile « aujourd'hui »).
	 */
	public function serie_courte_jours( $nb = 12 ) {
		global $wpdb;
		$t = IAM_Leads_Lead_Manager::table();

		$debut = gmdate( 'Y-m-d', strtotime( '-' . ( (int) $nb - 1 ) . ' days', current_time( 'timestamp' ) ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(date_creation) AS jour, COUNT(*) AS total
				 FROM {$t} WHERE date_creation >= %s
				 GROUP BY jour ORDER BY jour ASC",
				$debut . ' 00:00:00'
			)
		); // phpcs:ignore

		$par_jour = array();
		foreach ( $rows as $row ) {
			$par_jour[ $row->jour ] = (int) $row->total;
		}

		$sortie = array();
		for ( $i = (int) $nb - 1; $i >= 0; $i-- ) {
			$cle      = gmdate( 'Y-m-d', strtotime( '-' . $i . ' days', current_time( 'timestamp' ) ) );
			$sortie[] = $par_jour[ $cle ] ?? 0;
		}

		return $sortie;
	}

	/**
	 * Comptes mensuels des N derniers mois (sparkline de la tuile « ce mois-ci »).
	 */
	public function serie_courte_mois( $nb = 12 ) {
		global $wpdb;
		$t = IAM_Leads_Lead_Manager::table();

		$debut = gmdate( 'Y-m-01', strtotime( '-' . ( (int) $nb - 1 ) . ' months', current_time( 'timestamp' ) ) );

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(date_creation, '%%Y-%%m') AS mois, COUNT(*) AS total
				 FROM {$t} WHERE date_creation >= %s
				 GROUP BY mois ORDER BY mois ASC",
				$debut . ' 00:00:00'
			)
		); // phpcs:ignore

		$par_mois = array();
		foreach ( $rows as $row ) {
			$par_mois[ $row->mois ] = (int) $row->total;
		}

		$sortie = array();
		for ( $i = (int) $nb - 1; $i >= 0; $i-- ) {
			$cle      = gmdate( 'Y-m', strtotime( '-' . $i . ' months', current_time( 'timestamp' ) ) );
			$sortie[] = $par_mois[ $cle ] ?? 0;
		}

		return $sortie;
	}

	/**
	 * Répartition par colonne (programme, source, pays, statut, contact_prefere, reseau_social).
	 */
	public function repartition( $colonne, $du, $au, $limite = 0 ) {
		global $wpdb;

		$autorisees = array( 'programme', 'source', 'pays', 'statut', 'contact_prefere', 'reseau_social', 'utm_source' );
		if ( ! in_array( $colonne, $autorisees, true ) ) {
			return array( 'labels' => array(), 'valeurs' => array(), 'cles' => array() );
		}

		$t   = IAM_Leads_Lead_Manager::table();
		$sql = $wpdb->prepare(
			"SELECT {$colonne} AS cle, COUNT(*) AS total
			 FROM {$t}
			 WHERE date_creation BETWEEN %s AND %s AND {$colonne} <> ''
			 GROUP BY {$colonne} ORDER BY total DESC",
			$du . ' 00:00:00',
			$au . ' 23:59:59'
		);

		if ( $limite > 0 ) {
			$sql .= ' LIMIT ' . (int) $limite;
		}

		$rows = (array) $wpdb->get_results( $sql ); // phpcs:ignore

		$cles   = wp_list_pluck( $rows, 'cle' );
		$labels = $cles;

		if ( 'statut' === $colonne ) {
			$statuts = IAM_Leads::statuts();
			$labels  = array_map(
				static function ( $cle ) use ( $statuts ) {
					return $statuts[ $cle ] ?? $cle;
				},
				$cles
			);
		}

		return array(
			'labels'  => $labels,
			// Les clés brutes servent à colorer les statuts sans dépendre du libellé traduit.
			'cles'    => $cles,
			'valeurs' => array_map( 'intval', wp_list_pluck( $rows, 'total' ) ),
		);
	}

	/**
	 * Les N dernières soumissions.
	 */
	public function dernieres( $limite = 10 ) {
		global $wpdb;
		$t = IAM_Leads_Lead_Manager::table();

		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, date_creation, nom_complet, programme, source, statut FROM {$t} ORDER BY date_creation DESC LIMIT %d", (int) $limite ) ); // phpcs:ignore
	}

	/**
	 * Rassemble toutes les données pour la vue, avec cache.
	 *
	 * Le payload coûte une douzaine d'agrégats ; il est mis en cache pour
	 * TTL_CACHE secondes et purgé dès qu'un lead entre ou change de statut.
	 */
	public function payload( $periode, $du = '', $au = '' ) {
		$bornes = $this->bornes( $periode, $du, $au );
		$cle    = self::PREFIXE_CACHE . md5( $bornes['du'] . '|' . $bornes['au'] . '|' . $bornes['granularite'] );

		$cache = get_transient( $cle );
		if ( false !== $cache ) {
			return $cache;
		}

		$prec       = $this->bornes_precedentes( $bornes['du'], $bornes['au'] );
		$temporelle = $this->serie_temporelle( $bornes['du'], $bornes['au'], $bornes['granularite'] );

		/*
		 * Série de comparaison : tracée sur le même axe que la courbe
		 * courante, donc calée sur le même nombre de points.
		 */
		$temporelle_prec = $this->serie_temporelle( $prec['du'], $prec['au'], $bornes['granularite'] );
		$attendu         = count( $temporelle['valeurs'] );
		$valeurs_prec    = array_slice( $temporelle_prec['valeurs'], -$attendu );
		$valeurs_prec    = array_pad( $valeurs_prec, -$attendu, 0 );

		$data = array(
			'bornes'          => $bornes,
			'cartes'          => $this->cartes( $bornes['du'], $bornes['au'] ),
			'temporelle'      => $temporelle,
			'temporelle_prec' => $valeurs_prec,
			'spark_jours'     => $this->serie_courte_jours( 12 ),
			'spark_mois'      => $this->serie_courte_mois( 12 ),
			'programmes'      => $this->repartition( 'programme', $bornes['du'], $bornes['au'], 8 ),
			'sources'         => $this->repartition( 'source', $bornes['du'], $bornes['au'], 8 ),
			'reseaux'         => $this->repartition( 'reseau_social', $bornes['du'], $bornes['au'], 8 ),
			'contact_prefere' => $this->repartition( 'contact_prefere', $bornes['du'], $bornes['au'] ),
			'pays'            => $this->repartition( 'pays', $bornes['du'], $bornes['au'], 8 ),
			'statuts'         => $this->repartition( 'statut', $bornes['du'], $bornes['au'] ),
			'dernieres'       => $this->dernieres( 8 ),
		);

		set_transient( $cle, $data, self::TTL_CACHE );

		$this->memoriser_cle( $cle );

		return $data;
	}

	/**
	 * Garde la trace des transients posés, pour pouvoir les purger tous.
	 */
	private function memoriser_cle( $cle ) {
		$cles = (array) get_option( 'iam_leads_cache_cles', array() );

		if ( in_array( $cle, $cles, true ) ) {
			return;
		}

		$cles[] = $cle;

		// Borne haute : au-delà, les plus anciennes expireront d'elles-mêmes.
		if ( count( $cles ) > 60 ) {
			$cles = array_slice( $cles, -60 );
		}

		update_option( 'iam_leads_cache_cles', $cles, false );
	}

	/**
	 * Purge le cache du tableau de bord.
	 *
	 * Appelée à chaque nouveau lead et à chaque changement de statut : les
	 * chiffres affichés ne doivent jamais être en retard sur la réalité.
	 */
	public static function purger_cache() {
		foreach ( (array) get_option( 'iam_leads_cache_cles', array() ) as $cle ) {
			delete_transient( $cle );
		}

		update_option( 'iam_leads_cache_cles', array(), false );
	}
}
