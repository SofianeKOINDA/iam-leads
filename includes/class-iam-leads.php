<?php
/**
 * Classe principale du plugin.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads {

	/** @var IAM_Leads|null */
	private static $instance = null;

	/** @var IAM_Leads_Form_Renderer */
	public $renderer;

	/** @var IAM_Leads_Lead_Manager */
	public $leads;

	/** @var IAM_Leads_Dashboard */
	public $dashboard;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->leads     = new IAM_Leads_Lead_Manager();
		$this->renderer  = new IAM_Leads_Form_Renderer();
		$this->dashboard = new IAM_Leads_Dashboard();

		new IAM_Leads_UTM_Capture();
		new IAM_Leads_Export();
		new IAM_Leads_Admin_Menu();

		// Avant tout envoi, y compris ceux de WordPress lui-même.
		add_action( 'phpmailer_init', array( 'IAM_Leads_Emails', 'configurer_smtp' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public' ) );
		add_action( 'wp_head', array( $this, 'inline_utm' ), 5 );
	}

	/**
	 * Réglages par défaut.
	 */
	public static function default_settings() {
		return array(
			'admin_emails'          => get_option( 'admin_email' ),
			'email_confirmation'    => 1,
			'email_expediteur_nom'  => 'Groupe IAM',
			'email_expediteur_mail' => 'info@groupeiam.com',
			'smtp_active'           => 0,
			'smtp_hote'             => '',
			'smtp_port'             => 587,
			'smtp_securite'         => 'tls',
			'smtp_utilisateur'      => '',
			'smtp_motdepasse'       => '',
			'privacy_url'           => '',
			'plaquettes'            => array(),
			'crm_active'            => 0,
			'crm_fournisseur'       => 'hubspot',
			'hubspot_token'         => '',
			'whatsapp_active'       => 0,
			'whatsapp_fournisseur'  => 'meta',
			'whatsapp_mode'         => 'template',
			'whatsapp_langue'       => 'fr',
			'whatsapp_token'        => '',
			'whatsapp_phone_id'     => '',
			'whatsapp_template'     => 'iam_nouveau_lead',
			'whatsapp_expediteur'   => '',
			'whatsapp_destinataires'=> '',
			'delai_anti_spam'       => 3,
		);
	}

	/**
	 * Lecture d'un réglage.
	 *
	 * @param string $key     Clé.
	 * @param mixed  $default Valeur de repli.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		$settings = wp_parse_args( (array) get_option( 'iam_leads_settings', array() ), self::default_settings() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public static function get_settings() {
		return wp_parse_args( (array) get_option( 'iam_leads_settings', array() ), self::default_settings() );
	}

	/**
	 * Statuts de lead disponibles.
	 */
	public static function statuts() {
		return array(
			'nouveau'  => __( 'Nouveau', 'iam-leads' ),
			'contacte' => __( 'Contacté', 'iam-leads' ),
			'qualifie' => __( 'Qualifié', 'iam-leads' ),
			'converti' => __( 'Converti', 'iam-leads' ),
			'perdu'    => __( 'Perdu', 'iam-leads' ),
		);
	}

	/**
	 * Types de champs disponibles dans le constructeur.
	 */
	public static function types_champs() {
		return array(
			'nom'        => __( 'Nom (prénom + nom de famille)', 'iam-leads' ),
			'text'       => __( 'Texte', 'iam-leads' ),
			'email'      => __( 'E-mail', 'iam-leads' ),
			'tel'        => __( 'Téléphone', 'iam-leads' ),
			'select'     => __( 'Liste déroulante', 'iam-leads' ),
			'radio'      => __( 'Boutons radio', 'iam-leads' ),
			'checkbox'   => __( 'Cases à cocher', 'iam-leads' ),
			'textarea'   => __( 'Zone de texte', 'iam-leads' ),
			'date'       => __( 'Date', 'iam-leads' ),
			'hidden'     => __( 'Champ caché', 'iam-leads' ),
			'acceptance' => __( 'Consentement', 'iam-leads' ),
			'etape'      => __( '— Séparateur d\'étape —', 'iam-leads' ),
		);
	}

	/**
	 * Correspondances possibles entre un champ et une colonne de la table leads.
	 */
	public static function mappings() {
		return array(
			''                => __( '— Aucune —', 'iam-leads' ),
			'prenom'          => __( 'Prénom', 'iam-leads' ),
			'nom'             => __( 'Nom', 'iam-leads' ),
			'email'           => __( 'E-mail', 'iam-leads' ),
			'telephone'       => __( 'Téléphone', 'iam-leads' ),
			'pays'            => __( 'Pays', 'iam-leads' ),
			'programme'       => __( 'Programme', 'iam-leads' ),
			'niveau'          => __( 'Niveau d\'études', 'iam-leads' ),
			'contact_prefere' => __( 'Moyen de contact préféré', 'iam-leads' ),
			'source'          => __( 'Comment avez-vous connu l\'IAM ?', 'iam-leads' ),
			'reseau_social'   => __( 'Précision réseau social', 'iam-leads' ),
			'autre_source'    => __( 'Autre source', 'iam-leads' ),
			'consentement'    => __( 'Consentement', 'iam-leads' ),
		);
	}

	/**
	 * Enregistrement des styles et scripts publics (chargés par le rendu du formulaire).
	 */
	public function enqueue_public() {
		wp_register_style(
			'iam-leads',
			IAM_LEADS_URL . 'assets/css/iam-leads.css',
			array(),
			IAM_LEADS_VERSION
		);

		wp_register_script(
			'iam-leads-forms',
			IAM_LEADS_URL . 'assets/js/iam-forms.js',
			array(),
			IAM_LEADS_VERSION,
			true
		);

		wp_localize_script(
			'iam-leads-forms',
			'iamLeads',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'iam_leads_submit' ),
				'i18n'    => array(
					'envoi'    => __( 'Envoi en cours…', 'iam-leads' ),
					'erreur'   => __( 'La demande n\'a pas pu être envoyée. Vérifiez les champs signalés et réessayez.', 'iam-leads' ),
					'reseau'   => __( 'Connexion interrompue. Réessayez dans quelques instants.', 'iam-leads' ),
					'requis'   => __( 'Ce champ est obligatoire.', 'iam-leads' ),
					'emailKo'  => __( 'Saisissez une adresse e-mail valide.', 'iam-leads' ),
					/* translators: 1 : numéro de l'étape courante, 2 : nombre total d'étapes */
					'etape'    => __( 'Étape %1$d sur %2$d', 'iam-leads' ),
				),
			)
		);

	}

	/**
	 * Capture UTM, insérée directement dans la page.
	 *
	 * Ce script doit tourner sur TOUTES les pages du site, pas seulement
	 * celles qui portent un formulaire. En fichier séparé, cela coûtait une
	 * requête HTTP bloquante partout pour 3 Ko ; inséré dans le <head> il ne
	 * coûte plus rien et pose le cookie d'attribution au plus tôt.
	 */
	public function inline_utm() {
		static $source = null;

		if ( null === $source ) {
			$fichier = IAM_LEADS_PATH . 'assets/js/utm-capture.js';
			$source  = is_readable( $fichier ) ? (string) file_get_contents( $fichier ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		if ( '' === $source ) {
			return;
		}

		wp_print_inline_script_tag( $source, array( 'id' => 'iam-leads-utm' ) );
	}
}
