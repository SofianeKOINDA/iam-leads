<?php
/**
 * Plugin Name:       IAM Leads
 * Plugin URI:        https://groupeiam.com
 * Description:       Système de génération et de gestion de leads pour le Groupe IAM : constructeur de formulaires, capture UTM, téléchargement de plaquette, tableau de bord, CRM et notifications WhatsApp.
 * Version:           1.7.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Update URI:        https://github.com/SofianeKOINDA/iam-leads
 * Author:            Groupe IAM
 * Text Domain:       iam-leads
 * Domain Path:       /languages
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IAM_LEADS_VERSION', '1.7.0' );
define( 'IAM_LEADS_FILE', __FILE__ );
define( 'IAM_LEADS_PATH', plugin_dir_path( __FILE__ ) );
define( 'IAM_LEADS_URL', plugin_dir_url( __FILE__ ) );
define( 'IAM_LEADS_TABLE_FORMS', 'iam_forms' );
define( 'IAM_LEADS_TABLE_LEADS', 'iam_leads' );

require_once IAM_LEADS_PATH . 'includes/class-iam-leads.php';
require_once IAM_LEADS_PATH . 'includes/class-form-builder.php';
require_once IAM_LEADS_PATH . 'includes/class-form-renderer.php';
require_once IAM_LEADS_PATH . 'includes/class-lead-manager.php';
require_once IAM_LEADS_PATH . 'includes/class-utm-capture.php';
require_once IAM_LEADS_PATH . 'includes/class-dashboard.php';
require_once IAM_LEADS_PATH . 'includes/class-xlsx-writer.php';
require_once IAM_LEADS_PATH . 'includes/class-export.php';
require_once IAM_LEADS_PATH . 'includes/class-crm-integration.php';
require_once IAM_LEADS_PATH . 'includes/class-whatsapp.php';
require_once IAM_LEADS_PATH . 'includes/class-emails.php';
require_once IAM_LEADS_PATH . 'includes/class-logger.php';
require_once IAM_LEADS_PATH . 'admin/admin-menu.php';
require_once IAM_LEADS_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';

/**
 * Mises à jour automatiques depuis les Releases GitHub (tag vX.Y.Z), construites
 * par .github/workflows/release.yml. WordPress les propose comme pour tout
 * plugin et les installe seul si les mises à jour auto sont activées.
 * Dépôt privé : définir IAM_LEADS_GITHUB_TOKEN dans wp-config.php.
 *
 * @return YahnisElsts\PluginUpdateChecker\v5p7\Vcs\PluginUpdateChecker
 */
function iam_leads_updater() {
	static $checker = null;
	if ( null === $checker ) {
		$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/SofianeKOINDA/iam-leads/',
			IAM_LEADS_FILE,
			'iam-leads'
		);
		// Seul le ZIP attaché à la release est installé (dossier « iam-leads » à la racine).
		$checker->getVcsApi()->enableReleaseAssets(
			'/^iam-leads\.zip$/',
			YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS
		);
		if ( defined( 'IAM_LEADS_GITHUB_TOKEN' ) && IAM_LEADS_GITHUB_TOKEN ) {
			$checker->setAuthentication( IAM_LEADS_GITHUB_TOKEN );
		}
	}
	return $checker;
}
iam_leads_updater();

/**
 * Création / mise à jour des tables et du formulaire par défaut.
 */
function iam_leads_activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset  = $wpdb->get_charset_collate();
	$t_forms  = $wpdb->prefix . IAM_LEADS_TABLE_FORMS;
	$t_leads  = $wpdb->prefix . IAM_LEADS_TABLE_LEADS;

	$sql_forms = "CREATE TABLE {$t_forms} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		nom VARCHAR(191) NOT NULL DEFAULT '',
		champs LONGTEXT NULL,
		bouton_texte VARCHAR(191) NOT NULL DEFAULT 'Envoyer ma demande',
		message_confirmation TEXT NULL,
		reglages LONGTEXT NULL,
		plaquette_pdf BIGINT(20) UNSIGNED NULL,
		programme VARCHAR(191) NOT NULL DEFAULT '',
		date_creation DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id)
	) {$charset};";

	$sql_leads = "CREATE TABLE {$t_leads} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		form_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		donnees LONGTEXT NULL,
		nom_complet VARCHAR(191) NOT NULL DEFAULT '',
		email VARCHAR(191) NOT NULL DEFAULT '',
		telephone VARCHAR(64) NOT NULL DEFAULT '',
		pays VARCHAR(128) NOT NULL DEFAULT '',
		programme VARCHAR(191) NOT NULL DEFAULT '',
		niveau VARCHAR(191) NOT NULL DEFAULT '',
		contact_prefere VARCHAR(64) NOT NULL DEFAULT '',
		source VARCHAR(191) NOT NULL DEFAULT '',
		reseau_social VARCHAR(64) NOT NULL DEFAULT '',
		utm_source VARCHAR(191) NOT NULL DEFAULT '',
		utm_medium VARCHAR(191) NOT NULL DEFAULT '',
		utm_campaign VARCHAR(191) NOT NULL DEFAULT '',
		utm_content VARCHAR(191) NOT NULL DEFAULT '',
		utm_term VARCHAR(191) NOT NULL DEFAULT '',
		page_origine VARCHAR(255) NOT NULL DEFAULT '',
		page_soumission VARCHAR(255) NOT NULL DEFAULT '',
		ip VARCHAR(64) NOT NULL DEFAULT '',
		user_agent VARCHAR(255) NOT NULL DEFAULT '',
		statut VARCHAR(32) NOT NULL DEFAULT 'nouveau',
		crm_status VARCHAR(32) NOT NULL DEFAULT 'en_attente',
		whatsapp_status VARCHAR(32) NOT NULL DEFAULT 'en_attente',
		date_creation DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY form_id (form_id),
		KEY statut (statut),
		KEY programme (programme),
		KEY source (source),
		KEY date_creation (date_creation),
		KEY dc_statut (date_creation, statut),
		KEY dc_programme (date_creation, programme),
		KEY dc_source (date_creation, source),
		KEY dc_pays (date_creation, pays),
		KEY dc_contact (date_creation, contact_prefere),
		KEY dc_reseau (date_creation, reseau_social)
	) {$charset};";

	dbDelta( $sql_forms );
	dbDelta( $sql_leads );

	IAM_Leads_Form_Builder::seed_default_form();

	if ( false === get_option( 'iam_leads_settings' ) ) {
		add_option( 'iam_leads_settings', IAM_Leads::default_settings() );
	}

	iam_leads_synchroniser();
}

/**
 * Synchronisations qui dépendent des données, rejouées à l'activation comme
 * après chaque mise à jour : liste des programmes, plaquettes orphelines,
 * cache des agrégats, version installée.
 */
function iam_leads_synchroniser() {
	// La liste des programmes a pu changer depuis la création des formulaires.
	IAM_Leads_Form_Builder::sync_programme_options();

	// Les plaquettes sont indexées par intitulé de programme : on écarte les
	// entrées devenues orphelines, qui ne seraient plus ni affichées ni servies.
	// Les PDF eux-mêmes restent dans la médiathèque.
	$reglages = (array) get_option( 'iam_leads_settings', array() );
	if ( ! empty( $reglages['plaquettes'] ) && is_array( $reglages['plaquettes'] ) ) {
		$connues = array_intersect_key(
			$reglages['plaquettes'],
			array_flip( IAM_Leads_Form_Builder::programmes() )
		);

		if ( count( $connues ) !== count( $reglages['plaquettes'] ) ) {
			$reglages['plaquettes'] = $connues;
			update_option( 'iam_leads_settings', $reglages );
		}
	}

	// Le schéma ou les intitulés ont pu changer : les agrégats en cache ne sont plus fiables.
	IAM_Leads_Dashboard::purger_cache();

	update_option( 'iam_leads_version', IAM_LEADS_VERSION );
}
register_activation_hook( __FILE__, 'iam_leads_activate' );

/**
 * Mise à niveau silencieuse après mise à jour du plugin.
 *
 * Le hook d'activation ne se déclenche pas lors d'une simple mise à jour des
 * fichiers (FTP, Git ou mise à jour automatique). On compare donc la version
 * stockée à celle du code et, si elle a changé, on rejoue l'installation
 * complète : tables (dbDelta) puis synchronisations.
 */
function iam_leads_maybe_upgrade() {
	if ( IAM_LEADS_VERSION !== get_option( 'iam_leads_version' ) ) {
		iam_leads_activate();
	}
}
add_action( 'admin_init', 'iam_leads_maybe_upgrade' );

/**
 * Démarrage du plugin.
 */
function iam_leads_boot() {
	load_plugin_textdomain( 'iam-leads', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	IAM_Leads::instance();
}
add_action( 'plugins_loaded', 'iam_leads_boot' );
