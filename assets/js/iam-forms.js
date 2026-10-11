/* global iamLeads */
/*
 * IAM Leads — formulaires publics.
 *
 * Écrit sans jQuery : le script ne dépend plus d'aucune bibliothèque et
 * s'exécute même sur les pages où le thème ne charge pas jQuery.
 */
( function () {
	'use strict';

	/* ── Petits utilitaires ─────────────────────────────────────── */

	function tous( selecteur, portee ) {
		return Array.prototype.slice.call( ( portee || document ).querySelectorAll( selecteur ) );
	}

	function un( selecteur, portee ) {
		return ( portee || document ).querySelector( selecteur );
	}

	function estCoche( el ) {
		return 'radio' === el.type || 'checkbox' === el.type;
	}

	function visible( el ) {
		return !! ( el.offsetWidth || el.offsetHeight || el.getClientRects().length );
	}

	/**
	 * Valeur d'un groupe de champs partageant le même name.
	 */
	function valeurGroupe( elements ) {
		if ( ! elements.length ) {
			return '';
		}

		if ( estCoche( elements[ 0 ] ) ) {
			for ( var i = 0; i < elements.length; i++ ) {
				if ( elements[ i ].checked ) {
					return elements[ i ].value || '';
				}
			}

			return '';
		}

		return elements[ 0 ].value || '';
	}

	/* ── Champs conditionnels ───────────────────────────────────── */

	/**
	 * Affiche ou masque les champs dépendant d'une réponse précédente.
	 */
	function appliquerConditions( form ) {
		tous( '.iam-field-conditionnel', form ).forEach( function ( champ ) {
			var nom = champ.getAttribute( 'data-condition-field' );
			var attendu = String( champ.getAttribute( 'data-condition-value' ) );
			var sources = tous( '[name="' + nom + '"]', form );

			if ( ! sources.length ) {
				return;
			}

			var estVisible = String( valeurGroupe( sources ) ) === attendu;

			champ.hidden = ! estVisible;

			if ( ! estVisible ) {
				tous( 'input, select, textarea', champ ).forEach( function ( saisie ) {
					saisie.value = '';
				} );

				retirerErreur( champ );
			}
		} );
	}

	/* ── Erreurs ────────────────────────────────────────────────── */

	function retirerErreur( champ ) {
		if ( ! champ ) {
			return;
		}

		champ.classList.remove( 'is-erreur' );

		tous( '.iam-sous-champ', champ ).forEach( function ( el ) {
			el.classList.remove( 'is-erreur' );
		} );

		tous( '.iam-field-erreur', champ ).forEach( function ( el ) {
			el.textContent = '';
			el.hidden = true;
		} );
	}

	/**
	 * Affiche un message d'erreur, au niveau du sous-champ si celui-ci en a un.
	 */
	function afficherErreur( champ, message, nomChamp ) {
		champ.classList.add( 'is-erreur' );

		var cible = null;

		if ( nomChamp ) {
			cible = un( '.iam-field-erreur[data-pour="' + nomChamp + '"]', champ );

			var saisie = un( '[name="' + nomChamp + '"]', champ );
			var sousChamp = saisie ? saisie.closest( '.iam-sous-champ' ) : null;

			if ( sousChamp ) {
				sousChamp.classList.add( 'is-erreur' );
			}
		}

		if ( ! cible ) {
			cible = tous( '.iam-field-erreur', champ ).filter( function ( el ) {
				return ! el.hasAttribute( 'data-pour' );
			} )[ 0 ];
		}

		if ( ! cible ) {
			cible = un( '.iam-field-erreur', champ );
		}

		if ( cible ) {
			cible.textContent = message;
			cible.hidden = false;
		}
	}

	/* ── Validation ─────────────────────────────────────────────── */

	/**
	 * Validation côté client. Le serveur revalide systématiquement.
	 *
	 * @param {Element} portee Formulaire entier ou étape courante.
	 */
	function valider( portee ) {
		var valide = true;
		var premier = null;

		tous( '.iam-field', portee ).forEach( function ( champ ) {
			retirerErreur( champ );

			if ( champ.hidden ) {
				return;
			}

			// Groupes de choix : au moins une case cochée dans l'ensemble du champ.
			if ( champ.classList.contains( 'iam-field-radio' ) || champ.classList.contains( 'iam-field-checkbox' ) ) {
				if ( un( '[data-required="1"]', champ ) && ! un( 'input:checked', champ ) ) {
					afficherErreur( champ, iamLeads.i18n.requis );
					valide = false;
					premier = premier || champ;
				}

				return;
			}

			// Les autres champs peuvent contenir plusieurs saisies obligatoires
			// (c'est le cas du champ « Nom » : prénom et nom de famille).
			tous( '[data-required="1"]', champ ).forEach( function ( saisie ) {
				var estVide;

				if ( 'checkbox' === saisie.type ) {
					estVide = ! saisie.checked;
				} else {
					estVide = '' === String( saisie.value || '' ).trim();
				}

				if ( estVide ) {
					afficherErreur( champ, iamLeads.i18n.requis, saisie.getAttribute( 'name' ) );
					valide = false;
					premier = premier || champ;
					return;
				}

				if ( 'email' === saisie.type && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( saisie.value ) ) {
					afficherErreur( champ, iamLeads.i18n.emailKo, saisie.getAttribute( 'name' ) );
					valide = false;
					premier = premier || champ;
				}
			} );
		} );

		if ( premier ) {
			premier.scrollIntoView( { behavior: 'smooth', block: 'center' } );

			var aFocus = un( 'input, select, textarea', premier );

			if ( aFocus ) {
				aFocus.focus();
			}
		}

		return valide;
	}

	/* ── Messages et plaquette ──────────────────────────────────── */

	function message( wrap, texte, type ) {
		var zone = un( '.iam-form-message', wrap );

		if ( ! zone ) {
			return;
		}

		zone.classList.remove( 'is-succes', 'is-erreur' );
		zone.classList.add( 'is-' + type );
		zone.innerHTML = texte;
		zone.hidden = false;

		zone.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	function afficherPlaquette( wrap, plaquette ) {
		var zone = un( '.iam-form-plaquette', wrap );

		if ( ! zone || ! plaquette ) {
			return;
		}

		if ( plaquette.disponible ) {
			var lien = document.createElement( 'a' );

			lien.className = 'iam-plaquette-lien';
			lien.href = plaquette.url;
			lien.target = '_blank';
			lien.rel = 'noopener';
			lien.setAttribute( 'download', '' );
			lien.textContent = plaquette.libelle;

			zone.innerHTML = '';
			zone.appendChild( lien );
		} else {
			zone.textContent = plaquette.message;
		}

		zone.hidden = false;
	}

	/* ── Étapes ─────────────────────────────────────────────────── */

	/**
	 * Navigation entre les étapes d'un formulaire.
	 */
	function initEtapes( form, wrap ) {
		var etapes = tous( '.iam-step', form );

		if ( etapes.length < 2 ) {
			return;
		}

		var precedent = un( '.iam-nav-precedent', form );
		var suivant = un( '.iam-nav-suivant', form );
		var submit = un( '.iam-form-submit', form );
		var progression = un( '.iam-progression', wrap );
		var courante = 0;

		/**
		 * Une étape dont tous les champs sont masqués par une condition est sautée.
		 */
		function estUtile( index ) {
			var champs = tous( '.iam-field', etapes[ index ] );

			if ( ! champs.length ) {
				return false;
			}

			return champs.some( function ( champ ) {
				return ! champ.hidden;
			} );
		}

		function chercher( depart, sens ) {
			var i = depart;

			while ( i >= 0 && i < etapes.length ) {
				if ( estUtile( i ) ) {
					return i;
				}

				i += sens;
			}

			return -1;
		}

		function majProgression() {
			if ( ! progression ) {
				return;
			}

			var remplie = un( '.iam-progression-remplie', progression );

			if ( remplie ) {
				remplie.style.width = ( ( courante + 1 ) / etapes.length ) * 100 + '%';
			}

			tous( '.iam-point', progression ).forEach( function ( point ) {
				var index = parseInt( point.getAttribute( 'data-step' ), 10 );

				point.classList.toggle( 'is-actif', index === courante );
				point.classList.toggle( 'is-faite', index < courante );

				if ( index === courante ) {
					point.setAttribute( 'aria-current', 'step' );
				} else {
					point.removeAttribute( 'aria-current' );
				}
			} );

			var texte = un( '.iam-progression-texte', progression );

			if ( texte ) {
				texte.textContent = iamLeads.i18n.etape
					.replace( '%1$d', courante + 1 )
					.replace( '%2$d', etapes.length );
			}
		}

		function afficher( index, initial ) {
			courante = index;

			etapes.forEach( function ( etape, i ) {
				etape.hidden = i !== index;
			} );

			var estDerniere = -1 === chercher( index + 1, 1 );

			if ( precedent ) {
				precedent.hidden = -1 === chercher( index - 1, -1 );
			}

			if ( suivant ) {
				suivant.hidden = estDerniere;
			}

			if ( submit ) {
				submit.hidden = ! estDerniere;
			}

			majProgression();

			// Premier affichage (chargement de la page) : on ne déplace ni la page
			// ni le focus, le visiteur doit arriver en haut de la page.
			if ( initial ) {
				return;
			}

			// Changement d'étape : ramène le regard en haut du formulaire et
			// donne le focus au premier champ.
			form.scrollIntoView( { behavior: 'smooth', block: 'start' } );

			var aFocus = tous( 'input, select, textarea', etapes[ index ] ).filter( visible )[ 0 ];

			if ( aFocus ) {
				aFocus.focus();
			}
		}

		if ( suivant ) {
			suivant.addEventListener( 'click', function () {
				if ( ! valider( etapes[ courante ] ) ) {
					return;
				}

				var cible = chercher( courante + 1, 1 );

				if ( -1 !== cible ) {
					afficher( cible );
				}
			} );
		}

		if ( precedent ) {
			precedent.addEventListener( 'click', function () {
				var cible = chercher( courante - 1, -1 );

				if ( -1 !== cible ) {
					afficher( cible );
				}
			} );
		}

		// La touche Entrée fait avancer plutôt que de soumettre prématurément.
		form.addEventListener( 'keydown', function ( event ) {
			if ( 13 !== event.which && 'Enter' !== event.key ) {
				return;
			}

			if ( ! event.target.closest( 'input' ) ) {
				return;
			}

			if ( suivant && ! suivant.hidden ) {
				event.preventDefault();
				suivant.click();
			}
		} );

		afficher( 0, true );
	}

	/* ── Envoi ──────────────────────────────────────────────────── */

	function envoyer( form, wrap ) {
		var bouton = un( '.iam-form-submit', form );
		var libelle = bouton ? bouton.textContent : '';

		if ( bouton ) {
			bouton.disabled = true;
			bouton.textContent = iamLeads.i18n.envoi;
		}

		var donnees = new URLSearchParams( new FormData( form ) );

		donnees.append( 'action', 'iam_submit_form' );
		donnees.append( 'nonce', iamLeads.nonce );
		donnees.append( 'ouverture', form.getAttribute( 'data-ouverture' ) || '' );

		var statutOk = true;

		fetch( iamLeads.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: donnees.toString()
		} )
			.then( function ( reponse ) {
				// Le serveur répond en 4xx/5xx avec un corps JSON exploitable :
				// on lit le corps dans tous les cas avant de trancher.
				statutOk = reponse.ok;

				return reponse.json().catch( function () {
					return null;
				} );
			} )
			.then( function ( reponse ) {
				if ( statutOk && reponse && reponse.success ) {
					form.style.display = 'none';

					var progression = un( '.iam-progression', wrap );

					if ( progression ) {
						progression.style.display = 'none';
					}

					message( wrap, reponse.data.message, 'succes' );
					afficherPlaquette( wrap, reponse.data.plaquette );
					return;
				}

				var data = reponse && reponse.data;

				if ( data && data.champs ) {
					Object.keys( data.champs ).forEach( function ( nom ) {
						var saisie = un( '[name="' + nom + '"]', form );
						var champ = saisie ? saisie.closest( '.iam-field' ) : null;

						if ( ! champ ) {
							return;
						}

						afficherErreur( champ, data.champs[ nom ], nom );

						// Ramène l'utilisateur sur l'étape qui contient l'erreur.
						var etape = champ.closest( '.iam-step' );

						if ( etape && etape.hidden ) {
							tous( '.iam-step', form ).forEach( function ( el ) {
								el.hidden = true;
							} );

							etape.hidden = false;
						}
					} );
				}

				message( wrap, ( data && data.message ) || iamLeads.i18n.erreur, 'erreur' );
			} )
			.catch( function () {
				message( wrap, iamLeads.i18n.reseau, 'erreur' );
			} )
			.then( function () {
				if ( bouton ) {
					bouton.disabled = false;
					bouton.textContent = libelle;
				}
			} );
	}

	/* ── Démarrage ──────────────────────────────────────────────── */

	function init() {
		tous( '.iam-form' ).forEach( function ( form ) {
			var wrap = form.closest( '.iam-form-wrap' ) || form.parentNode;

			appliquerConditions( form );
			initEtapes( form, wrap );

			form.addEventListener( 'change', function ( event ) {
				var cible = event.target;

				if ( 'SELECT' === cible.tagName || estCoche( cible ) ) {
					appliquerConditions( form );
				}

				retirerErreur( cible.closest( '.iam-field' ) );
			} );

			form.addEventListener( 'input', function ( event ) {
				retirerErreur( event.target.closest( '.iam-field' ) );
			} );

			form.addEventListener( 'submit', function ( event ) {
				event.preventDefault();

				// À l'envoi, tout le formulaire est revalidé, étapes masquées comprises.
				if ( ! valider( form ) ) {
					return;
				}

				envoyer( form, wrap );
			} );
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
