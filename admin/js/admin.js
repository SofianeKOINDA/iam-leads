/*
 * IAM Leads — comportements communs des écrans d'administration.
 *  - copie d'un shortcode au clic (.iam-copiable) ;
 *  - onglets de sections ([data-iam-onglets]) : un panneau visible à la
 *    fois, l'onglet courant est gardé dans l'ancre de l'URL. Sans
 *    JavaScript, tous les panneaux restent affichés.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var code = e.target.closest( '.iam-copiable' );

		if ( ! code || ! navigator.clipboard ) {
			return;
		}

		navigator.clipboard.writeText( code.textContent.trim() ).then( function () {
			code.classList.add( 'is-copie' );
			setTimeout( function () {
				code.classList.remove( 'is-copie' );
			}, 1500 );
		} );
	} );

	Array.prototype.forEach.call( document.querySelectorAll( '[data-iam-onglets]' ), function ( barre ) {
		var onglets = Array.prototype.slice.call( barre.querySelectorAll( '[role="tab"]' ) );

		function panneau( onglet ) {
			return document.getElementById( onglet.getAttribute( 'aria-controls' ) );
		}

		function activer( cible, ancre ) {
			onglets.forEach( function ( onglet ) {
				var actif = onglet === cible;

				onglet.setAttribute( 'aria-selected', actif ? 'true' : 'false' );
				onglet.tabIndex = actif ? 0 : -1;

				if ( panneau( onglet ) ) {
					panneau( onglet ).hidden = ! actif;
				}
			} );

			if ( ancre && history.replaceState ) {
				history.replaceState( null, '', '#' + cible.getAttribute( 'aria-controls' ) );
			}
		}

		onglets.forEach( function ( onglet, i ) {
			onglet.addEventListener( 'click', function () {
				activer( onglet, true );
			} );

			onglet.addEventListener( 'keydown', function ( e ) {
				var pas = { ArrowRight: 1, ArrowLeft: -1 }[ e.key ];

				if ( ! pas ) {
					return;
				}

				var suivant = onglets[ ( i + pas + onglets.length ) % onglets.length ];
				suivant.focus();
				activer( suivant, true );
			} );
		} );

		// Un champ invalide dans un panneau masqué : on ouvre son onglet.
		var formulaire = barre.closest( 'form' ) || document;
		formulaire.addEventListener( 'invalid', function ( e ) {
			onglets.forEach( function ( onglet ) {
				var p = panneau( onglet );

				if ( p && p.hidden && p.contains( e.target ) ) {
					activer( onglet, true );
				}
			} );
		}, true );

		// L'onglet ouvert est renvoyé au serveur pour y revenir après l'enregistrement.
		var memo = formulaire.querySelector && formulaire.querySelector( 'input[name="_iam_onglet"]' );
		if ( memo ) {
			formulaire.addEventListener( 'submit', function () {
				var actif = barre.querySelector( '[aria-selected="true"]' );
				memo.value = actif ? actif.getAttribute( 'aria-controls' ) : '';
			} );
		}

		var depart = onglets.filter( function ( onglet ) {
			return '#' + onglet.getAttribute( 'aria-controls' ) === location.hash;
		} )[ 0 ] || onglets[ 0 ];

		activer( depart, false );
	} );
}() );
