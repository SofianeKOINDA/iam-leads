<?php
/**
 * Écriture d'un fichier XLSX sans dépendance externe.
 *
 * Un .xlsx est une archive ZIP contenant du XML. PhpSpreadsheet ferait grossir
 * le plugin de plusieurs mégaoctets et imposerait Composer sur le serveur, pour
 * un besoin qui tient en six fichiers.
 *
 * @package IAM_Leads
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IAM_Leads_XLSX_Writer {

	const NS      = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
	const NS_RELS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

	/**
	 * Colonnes : libellé et largeur.
	 *
	 * @var array
	 */
	private $colonnes;

	/**
	 * Feuille en cours d'écriture, hors de la mémoire : un export de plusieurs
	 * milliers de lignes tiendrait mal dans une chaîne.
	 *
	 * @var string
	 */
	private $chemin_feuille;

	/**
	 * @var resource|false
	 */
	private $handle;

	/**
	 * @var int
	 */
	private $index_ligne = 1;

	/**
	 * ZipArchive n'est pas garanti sur tous les hébergements.
	 */
	public static function disponible() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * @param array $colonnes Définitions de colonnes : label, largeur.
	 */
	public function __construct( array $colonnes ) {
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$this->colonnes       = array_values( $colonnes );
		$this->chemin_feuille = wp_tempnam( 'iam-leads-sheet' );
		$this->handle         = fopen( $this->chemin_feuille, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->ouvrir_feuille();
	}

	/**
	 * Ajoute une ligne de données.
	 *
	 * @param array $valeurs Valeurs, dans l'ordre des colonnes.
	 * @param int   $style   Index de style : 1 pour l'en-tête en gras.
	 */
	public function ligne( array $valeurs, $style = 0 ) {
		$cellules = '';

		foreach ( array_values( $valeurs ) as $index => $valeur ) {
			$texte = self::texte( $valeur );

			// Une cellule vide n'a pas besoin d'exister dans le XML.
			if ( '' === $texte ) {
				continue;
			}

			$cellules .= sprintf(
				'<c r="%s%d" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>',
				self::lettre( $index ),
				$this->index_ligne,
				$style ? ' s="' . (int) $style . '"' : '',
				$texte
			);
		}

		fwrite( $this->handle, '<row r="' . $this->index_ligne . '">' . $cellules . '</row>' );

		++$this->index_ligne;
	}

	/**
	 * Ferme la feuille et assemble l'archive.
	 *
	 * @return string Chemin du fichier produit, chaîne vide en cas d'échec.
	 */
	public function enregistrer() {
		$derniere = count( $this->colonnes ) - 1;
		$filtre   = $derniere >= 0 ? '<autoFilter ref="A1:' . self::lettre( $derniere ) . '1"/>' : '';

		fwrite( $this->handle, '</sheetData>' . $filtre . '</worksheet>' );
		fclose( $this->handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$chemin = wp_tempnam( 'iam-leads-xlsx' );
		$zip    = new ZipArchive();

		if ( true !== $zip->open( $chemin, ZipArchive::OVERWRITE ) ) {
			wp_delete_file( $this->chemin_feuille );

			return '';
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types() );
		$zip->addFromString( '_rels/.rels', self::rels() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_rels() );
		$zip->addFromString( 'xl/styles.xml', self::styles() );
		$zip->addFile( $this->chemin_feuille, 'xl/worksheets/sheet1.xml' );

		$ok = $zip->close();

		// La feuille n'est lue qu'au close() : la supprimer avant tronquerait
		// l'archive.
		wp_delete_file( $this->chemin_feuille );

		return $ok ? $chemin : '';
	}

	/* ---------------------------------------------------------------------
	 * Feuille
	 * ------------------------------------------------------------------ */

	/**
	 * En-tête de la feuille et ligne de titres.
	 *
	 * L'ordre des blocs est imposé par le schéma : sheetViews, cols, sheetData,
	 * puis autoFilter. Une inversion et Excel annonce un fichier corrompu sans
	 * indiquer pourquoi.
	 */
	private function ouvrir_feuille() {
		$cols = '';

		foreach ( $this->colonnes as $index => $colonne ) {
			$cols .= sprintf(
				'<col min="%1$d" max="%1$d" width="%2$d" customWidth="1"/>',
				$index + 1,
				max( 6, (int) ( $colonne['largeur'] ?? 18 ) )
			);
		}

		fwrite(
			$this->handle,
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="' . self::NS . '">'
			. '<sheetViews><sheetView workbookViewId="0">'
			. '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
			. '</sheetView></sheetViews>'
			. '<cols>' . $cols . '</cols>'
			. '<sheetData>'
		);

		$this->ligne( array_values( wp_list_pluck( $this->colonnes, 'label' ) ), 1 );
	}

	/**
	 * Prépare une valeur pour le XML.
	 *
	 * Tout est écrit en texte, y compris les dates et les téléphones : sinon
	 * Excel convertit « +221784820404 » en notation scientifique et réinterprète
	 * les dates selon la locale du poste. On perd le tri chronologique natif,
	 * mais aucune donnée n'est corrompue.
	 */
	private static function texte( $valeur ) {
		$valeur = is_scalar( $valeur ) ? (string) $valeur : '';

		// Les caractères de contrôle sont interdits en XML 1.0 : un seul suffit
		// à rendre le fichier illisible.
		$nettoye = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $valeur );
		$valeur  = null === $nettoye ? $valeur : $nettoye;

		return htmlspecialchars( $valeur, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/**
	 * Index de colonne vers lettre : 0 => A, 26 => AA.
	 */
	public static function lettre( $index ) {
		$lettre = '';

		for ( $i = (int) $index; $i >= 0; $i = intdiv( $i, 26 ) - 1 ) {
			$lettre = chr( 65 + ( $i % 26 ) ) . $lettre;
		}

		return $lettre;
	}

	/* ---------------------------------------------------------------------
	 * Parties statiques de l'archive
	 * ------------------------------------------------------------------ */

	private static function content_types() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
			. '</Types>';
	}

	private static function rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . self::NS_RELS . '/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private static function workbook() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="' . self::NS . '" xmlns:r="' . self::NS_RELS . '">'
			. '<sheets><sheet name="Leads" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private static function workbook_rels() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="' . self::NS_RELS . '/worksheet" Target="worksheets/sheet1.xml"/>'
			. '<Relationship Id="rId2" Type="' . self::NS_RELS . '/styles" Target="styles.xml"/>'
			. '</Relationships>';
	}

	/**
	 * Le strict minimum pour un en-tête en gras.
	 *
	 * Les blocs fonts, fills, borders, cellStyleXfs et cellXfs doivent tous être
	 * présents, même vides, sinon Excel refuse d'ouvrir le fichier. Le second
	 * remplissage « gray125 » est également attendu.
	 */
	private static function styles() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="' . self::NS . '">'
			. '<fonts count="2">'
			. '<font><sz val="11"/><name val="Calibri"/></font>'
			. '<font><b/><sz val="11"/><name val="Calibri"/></font>'
			. '</fonts>'
			. '<fills count="2">'
			. '<fill><patternFill patternType="none"/></fill>'
			. '<fill><patternFill patternType="gray125"/></fill>'
			. '</fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="2">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
			. '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}
}
