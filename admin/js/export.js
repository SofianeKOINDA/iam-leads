/**
 * Panneau d'export de la liste des leads.
 */
( function () {
	'use strict';

	var bouton   = document.getElementById( 'iam-export-bouton' );
	var panneau  = document.getElementById( 'iam-export-panneau' );

	if ( ! bouton || ! panneau ) {
		return;
	}

	var detail = document.getElementById( 'iam-export-detail' );

	function cases( selecteur ) {
		return Array.prototype.slice.call( panneau.querySelectorAll( selecteur ) );
	}

	/* Colonnes fixes : celles que pilotent les boutons Tout / Rien / Défaut.
	   Les colonnes de détail dépendent de leur propre interrupteur. */
	function casesFixes() {
		return cases( '.iam-export-groupe:not(.iam-export-detail) input[name="colonnes[]"]' );
	}

	function basculer( ouvrir ) {
		panneau.hidden = ! ouvrir;
		bouton.setAttribute( 'aria-expanded', ouvrir ? 'true' : 'false' );
	}

	bouton.addEventListener( 'click', function () {
		basculer( panneau.hidden );
	} );

	cases( '[data-selection]' ).forEach( function ( action ) {
		action.addEventListener( 'click', function () {
			var mode  = action.getAttribute( 'data-selection' );
			var defaut = ( panneau.getAttribute( 'data-defaut' ) || '' ).split( ',' );

			casesFixes().forEach( function ( item ) {
				if ( 'tout' === mode ) {
					item.checked = true;
				} else if ( 'rien' === mode ) {
					item.checked = false;
				} else {
					item.checked = defaut.indexOf( item.value ) !== -1;
				}
			} );
		} );
	} );

	/* L'interrupteur de détail grise ses colonnes sans les décocher : on
	   retrouve sa sélection en le réactivant. */
	function majDetail() {
		cases( '.iam-export-detail input[name="colonnes[]"]' ).forEach( function ( item ) {
			item.disabled = ! detail.checked;
		} );
	}

	if ( detail ) {
		detail.addEventListener( 'change', majDetail );
		majDetail();
	}

	panneau.addEventListener( 'submit', function ( evenement ) {
		var actives = cases( 'input[name="colonnes[]"]' ).filter( function ( item ) {
			return item.checked && ! item.disabled;
		} );

		if ( actives.length ) {
			return;
		}

		evenement.preventDefault();
		window.alert( window.iamExport ? window.iamExport.aucuneColonne : 'Sélectionnez au moins une colonne.' );
	} );

	// Un export refusé renvoie sur la liste : rouvrir le panneau évite de
	// chercher où était passée la sélection.
	if ( /[?&]message=export_/.test( window.location.search ) ) {
		basculer( true );
	}
}() );
