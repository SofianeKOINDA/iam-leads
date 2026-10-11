/* global jQuery, wp */
( function ( $ ) {
	'use strict';

	$( '.iam-plaquette-choisir' ).on( 'click', function ( event ) {
		event.preventDefault();

		var $ligne = $( this ).closest( '.iam-plaquette-ligne' );

		var cadre = wp.media( {
			title: 'Choisir la plaquette',
			button: { text: 'Utiliser ce fichier' },
			library: { type: 'application/pdf' },
			multiple: false
		} );

		cadre.on( 'select', function () {
			var piece = cadre.state().get( 'selection' ).first().toJSON();

			$ligne.find( '.iam-plaquette-id' ).val( piece.id );
			$ligne.find( '.iam-plaquette-nom' ).text( piece.filename );
		} );

		cadre.open();
	} );

	$( '.iam-plaquette-retirer' ).on( 'click', function ( event ) {
		event.preventDefault();

		var $ligne = $( this ).closest( '.iam-plaquette-ligne' );

		$ligne.find( '.iam-plaquette-id' ).val( 0 );
		$ligne.find( '.iam-plaquette-nom' ).text( 'Aucun fichier' );
	} );
} )( jQuery );
