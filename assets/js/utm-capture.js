/*
 * IAM Leads — capture de la source de trafic.
 * Ce script tourne sur toutes les pages : il mémorise les paramètres UTM
 * de la première visite pour qu'ils soient toujours disponibles au moment
 * où le visiteur remplit un formulaire, même plusieurs pages plus tard.
 */
( function () {
	'use strict';

	var COOKIE = 'iam_utm';
	var COOKIE_REF = 'iam_ref';
	var JOURS = 30;
	var CLES = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ];

	function poser( nom, valeur ) {
		var expiration = new Date();

		expiration.setTime( expiration.getTime() + JOURS * 24 * 60 * 60 * 1000 );

		document.cookie = nom + '=' + encodeURIComponent( valeur ) +
			';expires=' + expiration.toUTCString() +
			';path=/;SameSite=Lax' +
			( 'https:' === window.location.protocol ? ';Secure' : '' );
	}

	function lire( nom ) {
		var parties = document.cookie.split( ';' );

		for ( var i = 0; i < parties.length; i++ ) {
			var paire = parties[ i ].trim();

			if ( 0 === paire.indexOf( nom + '=' ) ) {
				return decodeURIComponent( paire.substring( nom.length + 1 ) );
			}
		}

		return '';
	}

	/**
	 * Les paramètres de l'URL courante écrasent le cookie : une nouvelle
	 * campagne doit remplacer l'ancienne attribution.
	 */
	function enregistrerUtm() {
		var params = new URLSearchParams( window.location.search );
		var trouves = {};
		var aUnParametre = false;

		CLES.forEach( function ( cle ) {
			var valeur = params.get( cle );

			if ( valeur ) {
				trouves[ cle ] = valeur;
				aUnParametre = true;
			}
		} );

		if ( aUnParametre ) {
			poser( COOKIE, JSON.stringify( trouves ) );
		}
	}

	/**
	 * Mémorise le référent externe de la première page vue.
	 */
	function enregistrerReferent() {
		if ( lire( COOKIE_REF ) || ! document.referrer ) {
			return;
		}

		try {
			var origine = new URL( document.referrer );

			if ( origine.hostname !== window.location.hostname ) {
				poser( COOKIE_REF, document.referrer );
			}
		} catch ( e ) {
			// Référent illisible : on l'ignore.
		}
	}

	/**
	 * Renseigne les champs cachés des formulaires présents sur la page.
	 */
	function remplirFormulaires() {
		var stockees = {};

		try {
			stockees = JSON.parse( lire( COOKIE ) || '{}' );
		} catch ( e ) {
			stockees = {};
		}

		var params = new URLSearchParams( window.location.search );

		document.querySelectorAll( '.iam-form' ).forEach( function ( form ) {
			CLES.forEach( function ( cle ) {
				var champ = form.querySelector( '[data-iam-utm="' + cle + '"]' );

				if ( champ ) {
					champ.value = params.get( cle ) || stockees[ cle ] || '';
				}
			} );

			var origine = form.querySelector( '[name="page_origine"]' );
			var soumission = form.querySelector( '[name="page_soumission"]' );

			if ( origine ) {
				origine.value = lire( COOKIE_REF ) || document.referrer || '';
			}

			if ( soumission ) {
				soumission.value = window.location.href;
			}
		} );
	}

	enregistrerUtm();
	enregistrerReferent();

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', remplirFormulaires );
	} else {
		remplirFormulaires();
	}

	window.iamUtm = { remplirFormulaires: remplirFormulaires };
} )();
