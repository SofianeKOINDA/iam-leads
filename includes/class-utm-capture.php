<?php
/**
 * Capture des paramètres UTM et des métadonnées de visite.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_UTM_Capture {

	const COOKIE     = 'iam_utm';
	const COOKIE_REF = 'iam_ref';
	const DUREE      = 30 * DAY_IN_SECONDS;

	public function __construct() {
		add_action( 'init', array( $this, 'poser_cookie' ), 1 );
	}

	/**
	 * Paramètres UTM suivis.
	 */
	public static function cles() {
		return array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' );
	}

	/**
	 * Pose le cookie UTM côté serveur dès la première page vue.
	 * Le script JS fait la même chose côté client (utile avec un cache de pages).
	 */
	public function poser_cookie() {
		if ( is_admin() || wp_doing_ajax() || headers_sent() ) {
			return;
		}

		$trouves = array();
		foreach ( self::cles() as $cle ) {
			if ( ! empty( $_GET[ $cle ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$trouves[ $cle ] = sanitize_text_field( wp_unslash( $_GET[ $cle ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}

		if ( $trouves ) {
			setcookie( self::COOKIE, wp_json_encode( $trouves ), time() + self::DUREE, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		}

		if ( empty( $_COOKIE[ self::COOKIE_REF ] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$referer = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			if ( wp_parse_url( $referer, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
				setcookie( self::COOKIE_REF, $referer, time() + self::DUREE, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
			}
		}
	}

	/**
	 * Reconstitue les métadonnées au moment de la soumission.
	 * Ordre de priorité : champs cachés du formulaire, puis cookie.
	 *
	 * @return array
	 */
	public static function depuis_requete() {
		$sortie = array();
		$cookie = array();

		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$decode = json_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ), true );
			if ( is_array( $decode ) ) {
				$cookie = $decode;
			}
		}

		foreach ( self::cles() as $cle ) {
			$valeur = '';
			if ( ! empty( $_POST[ $cle ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$valeur = sanitize_text_field( wp_unslash( $_POST[ $cle ] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			} elseif ( ! empty( $cookie[ $cle ] ) ) {
				$valeur = sanitize_text_field( $cookie[ $cle ] );
			}
			$sortie[ $cle ] = substr( $valeur, 0, 191 );
		}

		$origine = '';
		if ( ! empty( $_POST['page_origine'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$origine = esc_url_raw( wp_unslash( $_POST['page_origine'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		} elseif ( ! empty( $_COOKIE[ self::COOKIE_REF ] ) ) {
			$origine = esc_url_raw( wp_unslash( $_COOKIE[ self::COOKIE_REF ] ) );
		} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			$origine = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		$soumission = '';
		if ( ! empty( $_POST['page_soumission'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$soumission = esc_url_raw( wp_unslash( $_POST['page_soumission'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		$sortie['page_origine']    = substr( $origine, 0, 255 );
		$sortie['page_soumission'] = substr( $soumission, 0, 255 );

		return $sortie;
	}

	/**
	 * Adresse IP du visiteur, en tenant compte du reverse proxy Nginx.
	 */
	public static function ip_visiteur() {
		$candidats = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $candidats as $cle ) {
			if ( empty( $_SERVER[ $cle ] ) ) {
				continue;
			}

			$brut = sanitize_text_field( wp_unslash( $_SERVER[ $cle ] ) );

			foreach ( explode( ',', $brut ) as $ip ) {
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '';
	}
}
