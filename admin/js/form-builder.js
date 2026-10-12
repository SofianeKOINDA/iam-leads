/* global jQuery, wp, iamBuilder */
( function ( $ ) {
	'use strict';

	var $liste = $( '#iam-champs-liste' );

	if ( ! $liste.length ) {
		return;
	}

	var compteur = $liste.find( '.iam-champ' ).length;

	/**
	 * Convertit une étiquette en identifiant technique.
	 */
	function versIdentifiant( texte ) {
		return texte
			.toString()
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '_' )
			.replace( /^_+|_+$/g, '' )
			.substring( 0, 50 );
	}

	/**
	 * Réécrit les index des champs après un ajout, un retrait ou un déplacement.
	 */
	function renumeroter() {
		// Le serveur compare ce total au nombre de champs reçus pour détecter
		// une requête coupée par max_input_vars.
		var total = $liste.find( '.iam-champ' ).length;

		$( '#iam-champs-total' ).val( total );
		$( '#iam-champs-compte' ).text( total + ( total > 1 ? ' champs' : ' champ' ) );

		$liste.find( '.iam-champ' ).each( function ( index ) {
			$( this ).attr( 'data-index', index );

			$( this ).find( '[name^="champs["]' ).each( function () {
				var nom = $( this ).attr( 'name' );
				$( this ).attr( 'name', nom.replace( /champs\[[^\]]*\]/, 'champs[' + index + ']' ) );
			} );
		} );
	}

	/**
	 * Affiche uniquement les réglages utiles au type sélectionné.
	 */
	function adapterChamp( $champ ) {
		var type = $champ.find( '.iam-champ-type-select' ).val();
		var avecOptions = [ 'select', 'radio', 'checkbox' ].indexOf( type ) !== -1;
		var estEtape = type === 'etape';
		var estNom = type === 'nom';

		$champ.find( '.iam-champ-options' ).toggle( avecOptions );
		$champ.find( '.iam-champ-valeur' ).toggle( type === 'hidden' );
		$champ.find( '.iam-champ-sous-labels' ).toggle( estNom );
		$champ.find( '.iam-champ-description' ).toggle( estEtape );

		// Un séparateur ne collecte rien : ni identifiant, ni obligation, ni condition.
		$champ.find( '.iam-champ-identifiant, .iam-champ-requis, .iam-champ-condition' ).toggle( ! estEtape );

		// Le champ Nom occupe toujours la pleine largeur et mappe prénom + nom.
		$champ.find( '.iam-champ-mapping, .iam-champ-largeur, .iam-champ-placeholder' ).toggle( ! estEtape && ! estNom );

		$champ.toggleClass( 'iam-champ-separateur', estEtape );
		$champ.find( '.iam-champ-type' ).text( iamBuilder.types[ type ] || type );
	}

	// Ajouter un champ.
	$( '#iam-ajouter-champ' ).on( 'click', function () {
		var gabarit = $( '#tmpl-iam-champ' ).html().replace( /__INDEX__/g, compteur );

		compteur++;

		var $nouveau = $( gabarit );
		$liste.append( $nouveau );
		$nouveau.find( '.iam-champ-corps' ).prop( 'hidden', false );
		adapterChamp( $nouveau );
		renumeroter();

		$nouveau[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
		$nouveau.find( '.iam-champ-label' ).trigger( 'focus' );
	} );

	// Ouvrir / replier un champ.
	$liste.on( 'click', '.iam-basculer', function () {
		var $corps = $( this ).closest( '.iam-champ' ).find( '.iam-champ-corps' );
		var ouvert = ! $corps.prop( 'hidden' );

		$corps.prop( 'hidden', ouvert );
		$( this ).text( ouvert ? 'Ouvrir' : 'Replier' );
	} );

	// Retirer un champ.
	$liste.on( 'click', '.iam-supprimer', function () {
		if ( ! window.confirm( iamBuilder.i18n.confirmer ) ) {
			return;
		}

		$( this ).closest( '.iam-champ' ).remove();
		renumeroter();
	} );

	// Déplacer un champ.
	$liste.on( 'click', '.iam-monter', function () {
		var $champ = $( this ).closest( '.iam-champ' );
		$champ.prev( '.iam-champ' ).before( $champ );
		renumeroter();
	} );

	$liste.on( 'click', '.iam-descendre', function () {
		var $champ = $( this ).closest( '.iam-champ' );
		$champ.next( '.iam-champ' ).after( $champ );
		renumeroter();
	} );

	// Titre et identifiant tenus à jour pendant la saisie.
	$liste.on( 'input', '.iam-champ-label', function () {
		var $champ = $( this ).closest( '.iam-champ' );
		var valeur = $( this ).val();
		var $name = $champ.find( '.iam-champ-name' );

		$champ.find( '.iam-champ-titre' ).text( valeur || iamBuilder.i18n.nouveauChamp );

		if ( ! $name.data( 'modifie' ) ) {
			$name.val( versIdentifiant( valeur ) );
		}
	} );

	$liste.on( 'input', '.iam-champ-name', function () {
		$( this ).data( 'modifie', true );
		$( this ).val( versIdentifiant( $( this ).val() ) );
	} );

	$liste.on( 'change', '.iam-champ-type-select', function () {
		adapterChamp( $( this ).closest( '.iam-champ' ) );
	} );

	/**
	 * Ouvre le bloc d'un champ et aligne le libellé du bouton.
	 */
	function ouvrir( $champ ) {
		$champ.find( '.iam-champ-corps' ).prop( 'hidden', false );
		$champ.find( '.iam-basculer' ).text( 'Replier' );
	}

	/*
	 * Un champ sans étiquette est écarté par le serveur : sans étiquette, pas
	 * d'identifiant technique, donc rien à quoi rattacher une réponse. Le
	 * formulaire partait quand même et revenait vidé de ses champs, avec un
	 * message de succès. On bloque l'envoi et on désigne le champ fautif.
	 */
	$( '#iam-form-edit' ).on( 'submit', function ( event ) {
		var $fautif = null;

		$liste.find( '.iam-champ' ).each( function () {
			var $champ = $( this );

			if ( $fautif || 'etape' === $champ.find( '.iam-champ-type-select' ).val() ) {
				return;
			}

			var label = $.trim( $champ.find( '.iam-champ-label' ).val() || '' );
			var name  = $.trim( $champ.find( '.iam-champ-name' ).val() || '' );

			if ( '' === label && '' === name ) {
				$fautif = $champ;
			}
		} );

		if ( ! $fautif ) {
			return;
		}

		event.preventDefault();

		$( '#iam-champs-alerte' ).text( iamBuilder.i18n.labelManquant ).prop( 'hidden', false );

		ouvrir( $fautif );
		$fautif[ 0 ].scrollIntoView( { behavior: 'smooth', block: 'center' } );
		$fautif.find( '.iam-champ-label' ).trigger( 'focus' );
	} );

	// L'alerte disparaît dès que l'étiquette manquante est saisie.
	$liste.on( 'input', '.iam-champ-label', function () {
		if ( $.trim( $( this ).val() ) ) {
			$( '#iam-champs-alerte' ).prop( 'hidden', true );
		}
	} );

	// Sélecteurs de couleur du bouton. Le bouton « Effacer » d'Iris vide le
	// champ, ce qui fait retomber le formulaire sur la couleur du thème.
	if ( $.fn.wpColorPicker ) {
		$( '.iam-couleur' ).wpColorPicker();
	}

	// Plaquette par défaut du formulaire.
	var mediatheque = null;

	$( '#iam-choisir-plaquette' ).on( 'click', function ( event ) {
		event.preventDefault();

		if ( ! mediatheque ) {
			mediatheque = wp.media( {
				title: iamBuilder.i18n.choisirPdf,
				button: { text: iamBuilder.i18n.utiliser },
				library: { type: 'application/pdf' },
				multiple: false
			} );

			mediatheque.on( 'select', function () {
				var piece = mediatheque.state().get( 'selection' ).first().toJSON();

				$( '#iam-plaquette-id' ).val( piece.id );
				$( '#iam-plaquette-nom' ).text( piece.filename );
			} );
		}

		mediatheque.open();
	} );

	$( '#iam-retirer-plaquette' ).on( 'click', function ( event ) {
		event.preventDefault();

		$( '#iam-plaquette-id' ).val( 0 );
		$( '#iam-plaquette-nom' ).text( 'Aucun fichier' );
	} );

	// État initial.
	$liste.find( '.iam-champ' ).each( function () {
		adapterChamp( $( this ) );
	} );
} )( jQuery );
