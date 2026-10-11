<?php
/**
 * Journal des intégrations (CRM, WhatsApp).
 * Conserve les 200 dernières entrées dans une option, sans fichier à gérer sur le VPS.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_Logger {

	const OPTION = 'iam_leads_journal';
	const MAX    = 200;

	/**
	 * Ajoute une entrée au journal.
	 *
	 * @param string $canal   crm|whatsapp|email.
	 * @param string $niveau  succes|echec|info.
	 * @param string $message Message lisible.
	 * @param int    $lead_id Lead concerné.
	 */
	public static function ajouter( $canal, $niveau, $message, $lead_id = 0 ) {
		$journal = (array) get_option( self::OPTION, array() );

		array_unshift(
			$journal,
			array(
				'date'    => current_time( 'mysql' ),
				'canal'   => $canal,
				'niveau'  => $niveau,
				'message' => is_scalar( $message ) ? (string) $message : wp_json_encode( $message ),
				'lead_id' => (int) $lead_id,
			)
		);

		update_option( self::OPTION, array_slice( $journal, 0, self::MAX ), false );
	}

	/**
	 * Retourne le journal.
	 */
	public static function lire( $limite = 50 ) {
		return array_slice( (array) get_option( self::OPTION, array() ), 0, (int) $limite );
	}

	/**
	 * Vide le journal.
	 */
	public static function vider() {
		update_option( self::OPTION, array(), false );
	}
}
