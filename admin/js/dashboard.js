/* global Chart */
/*
 * Tableau de bord IAM Leads.
 *
 * Règles de couleur appliquées ici (vérifiées par l'outil de validation
 * daltonisme / contraste, cf. README) :
 *  - une série unique = UNE seule couleur. L'ancienne version appliquait
 *    dix teintes en boucle à une seule série, ce qui encodait le rang en
 *    couleur et rendait la lecture aléatoire.
 *  - les statuts portent cinq teintes sémantiques, identiques aux badges.
 *    Le couple converti/perdu étant proche en vision deutéranope, les
 *    libellés d'axe et les valeurs chiffrées sont obligatoires.
 */
( function () {
	'use strict';

	var data = window.iamDashboardData;

	if ( ! data || typeof Chart === 'undefined' ) {
		return;
	}

	var i18n = data.i18n || {};

	var C = {
		serie: '#2a78d6',
		wash: 'rgba(42,120,214,.10)',
		grille: '#e8e8ea',
		axe: '#c3c2b7',
		muted: '#787c82',
		ink2: '#50575e',
		deemph: '#c9cdd2',
		surface: '#ffffff'
	};

	// Rampe ordinale à teinte unique, pour la barre part-à-tout.
	var RAMPE = [ '#2a78d6', '#5598e7', '#86b6ef', '#b7d3f6' ];

	// Clés brutes → teinte. Indépendant du libellé traduit.
	var STATUT = {
		nouveau: '#2a78d6',
		contacte: '#eda100',
		qualifie: '#4a3aa7',
		converti: '#008300',
		perdu: '#e34948'
	};

	/* ── Réglages globaux ───────────────────────────────────────── */

	Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
	Chart.defaults.font.size = 11.5;
	Chart.defaults.color = C.muted;
	Chart.defaults.animation.duration = 320;
	Chart.defaults.plugins.tooltip.backgroundColor = '#1d2327';
	Chart.defaults.plugins.tooltip.padding = 9;
	Chart.defaults.plugins.tooltip.cornerRadius = 5;
	Chart.defaults.plugins.tooltip.titleFont = { size: 11.5, weight: '600' };
	Chart.defaults.plugins.tooltip.bodyFont = { size: 11.5 };
	Chart.defaults.plugins.tooltip.displayColors = false;

	function fr( n ) {
		return Number( n ).toLocaleString( undefined );
	}

	function vide( conteneur ) {
		var p = document.createElement( 'p' );

		p.className = 'iam-graph-vide';
		p.textContent = i18n.vide || 'Pas encore de données sur cette période.';
		conteneur.innerHTML = '';
		conteneur.appendChild( p );
	}

	/* ── Sparklines : SVG inline, pas d'instance Chart.js ───────── */

	function sparkline( svg, points, couleur ) {
		var w = +svg.getAttribute( 'width' );
		var h = +svg.getAttribute( 'height' );
		var pad = 3;

		if ( ! points || points.length < 2 ) {
			return;
		}

		var min = Math.min.apply( null, points );
		var max = Math.max.apply( null, points );
		var etendue = ( max - min ) || 1;

		var x = function ( i ) {
			return pad + i * ( w - pad * 2 ) / ( points.length - 1 );
		};
		var y = function ( v ) {
			return h - pad - ( v - min ) / etendue * ( h - pad * 2 );
		};

		var trace = points.map( function ( v, i ) {
			return ( i ? 'L' : 'M' ) + x( i ).toFixed( 1 ) + ' ' + y( v ).toFixed( 1 );
		} ).join( ' ' );

		var aire = trace +
			' L' + x( points.length - 1 ).toFixed( 1 ) + ' ' + ( h - pad ) +
			' L' + x( 0 ).toFixed( 1 ) + ' ' + ( h - pad ) + ' Z';

		svg.setAttribute( 'viewBox', '0 0 ' + w + ' ' + h );
		svg.innerHTML =
			'<path d="' + aire + '" fill="' + couleur + '" opacity=".10"/>' +
			'<path d="' + trace + '" fill="none" stroke="' + couleur + '" stroke-width="1.6" ' +
				'stroke-linejoin="round" stroke-linecap="round"/>' +
			// Anneau 2px en couleur de surface sur le point courant.
			'<circle cx="' + x( points.length - 1 ).toFixed( 1 ) + '" ' +
				'cy="' + y( points[ points.length - 1 ] ).toFixed( 1 ) + '" r="2.8" ' +
				'fill="' + couleur + '" stroke="' + C.surface + '" stroke-width="2"/>';
	}

	var SPARKS = {
		temporelle: ( data.temporelle && data.temporelle.valeurs ) ? data.temporelle.valeurs.slice( -12 ) : [],
		jours: data.spark_jours || [],
		mois: data.spark_mois || []
	};

	Array.prototype.forEach.call( document.querySelectorAll( '.iam-sparkline' ), function ( svg ) {
		sparkline( svg, SPARKS[ svg.getAttribute( 'data-spark' ) ], C.serie );
	} );

	/* ── Étiquettes de valeur au bout des barres ────────────────────
	   C'est l'encodage secondaire exigé par la validation : certaines
	   teintes passent sous 3:1 sur fond blanc, la valeur chiffrée doit
	   donc rester lisible sans dépendre de la couleur.               */

	var etiquettesValeur = {
		id: 'iamEtiquettes',
		afterDatasetsDraw: function ( chart ) {
			var ctx = chart.ctx;

			ctx.save();
			ctx.font = '600 11.5px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
			ctx.fillStyle = C.ink2;
			ctx.textBaseline = 'middle';
			ctx.textAlign = 'left';

			chart.getDatasetMeta( 0 ).data.forEach( function ( barre, i ) {
				ctx.fillText( fr( chart.data.datasets[ 0 ].data[ i ] ), barre.x + 7, barre.y );
			} );

			ctx.restore();
		}
	};

	/* ── Courbe temporelle ──────────────────────────────────────── */

	function libellesTemps( labels, granularite ) {
		if ( 'jour' !== granularite ) {
			return labels;
		}

		return labels.map( function ( iso ) {
			var p = String( iso ).split( '-' );
			return p.length === 3 ? ( p[ 2 ] + '/' + p[ 1 ] ) : iso;
		} );
	}

	( function () {
		var canvas = document.getElementById( 'iam-chart-temps' );

		if ( ! canvas ) {
			return;
		}

		var jeu = data.temporelle || { labels: [], valeurs: [] };

		if ( ! jeu.valeurs.length ) {
			vide( canvas.parentNode );
			return;
		}

		var jeux = [ {
			label: i18n.leads || 'Leads',
			data: jeu.valeurs,
			borderColor: C.serie,
			backgroundColor: C.wash,
			borderWidth: 2,
			fill: true,
			tension: .32,
			pointRadius: 0,
			pointHoverRadius: 5,
			pointHoverBackgroundColor: C.serie,
			pointHoverBorderColor: C.surface,
			pointHoverBorderWidth: 2,
			order: 1
		} ];

		if ( data.temporelle_prec && data.temporelle_prec.length ) {
			jeux.push( {
				label: i18n.precedente || 'Période précédente',
				data: data.temporelle_prec,
				borderColor: C.deemph,
				borderWidth: 1.5,
				borderDash: [ 4, 4 ],
				pointRadius: 0,
				pointHoverRadius: 0,
				fill: false,
				tension: .32,
				order: 2
			} );
		}

		new Chart( canvas, {
			type: 'line',
			data: {
				labels: libellesTemps( jeu.labels, ( data.bornes || {} ).granularite ),
				datasets: jeux
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: { mode: 'index', intersect: false },
				layout: { padding: { top: 6, right: 4 } },
				plugins: {
					legend: { display: false },
					tooltip: {
						displayColors: true,
						boxWidth: 8,
						boxHeight: 8,
						boxPadding: 4,
						usePointStyle: true,
						callbacks: {
							label: function ( ctx ) {
								return ' ' + ctx.dataset.label + ' : ' + fr( ctx.parsed.y );
							}
						}
					}
				},
				scales: {
					y: {
						beginAtZero: true,
						border: { display: false },
						grid: { color: C.grille, drawTicks: false },
						ticks: { precision: 0, padding: 8, maxTicksLimit: 5 }
					},
					x: {
						border: { color: C.axe },
						grid: { display: false },
						ticks: { padding: 6, autoSkip: true, maxRotation: 0 }
					}
				}
			}
		} );
	} )();

	/* ── Barres horizontales, série unique ──────────────────────── */

	function barres( id, jeu, couleurs ) {
		var canvas = document.getElementById( id );

		if ( ! canvas ) {
			return;
		}

		if ( ! jeu || ! jeu.valeurs || ! jeu.valeurs.length ) {
			vide( canvas.parentNode );
			return;
		}

		new Chart( canvas, {
			type: 'bar',
			data: {
				labels: jeu.labels,
				datasets: [ {
					label: i18n.leads || 'Leads',
					data: jeu.valeurs,
					backgroundColor: couleurs || C.serie,
					borderRadius: { topLeft: 0, bottomLeft: 0, topRight: 4, bottomRight: 4 },
					borderSkipped: false,
					maxBarThickness: 18,
					categoryPercentage: .82,
					barPercentage: .9
				} ]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				maintainAspectRatio: false,
				layout: { padding: { right: 40 } },
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function ( ctx ) {
								return ' ' + fr( ctx.parsed.x ) + ' ' + ( i18n.leadsMin || 'leads' );
							}
						}
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						border: { display: false },
						// Rien à ancrer : les valeurs sont écrites sur les barres.
						grid: { display: false },
						ticks: { display: false }
					},
					y: {
						border: { color: C.axe },
						grid: { display: false },
						ticks: { padding: 8, color: C.ink2, font: { size: 12 } }
					}
				}
			},
			plugins: [ etiquettesValeur ]
		} );
	}

	barres( 'iam-chart-programmes', data.programmes );
	barres( 'iam-chart-sources', data.sources );
	barres( 'iam-chart-pays', data.pays );
	barres( 'iam-chart-reseaux', data.reseaux );

	barres(
		'iam-chart-statuts',
		data.statuts,
		( ( data.statuts && data.statuts.cles ) || [] ).map( function ( cle ) {
			return STATUT[ cle ] || C.serie;
		} )
	);

	/* ── Part-à-tout : barre empilée horizontale ────────────────── */

	( function () {
		var barre = document.getElementById( 'iam-barre-contact' );
		var legende = document.getElementById( 'iam-legende-contact' );
		var jeu = data.contact_prefere;

		if ( ! barre || ! legende ) {
			return;
		}

		if ( ! jeu || ! jeu.valeurs || ! jeu.valeurs.length ) {
			vide( barre.parentNode.querySelector( '.iam-barre-part' ) ? barre : barre );
			legende.remove();
			return;
		}

		var total = jeu.valeurs.reduce( function ( a, b ) {
			return a + b;
		}, 0 );

		var resume = [];

		jeu.labels.forEach( function ( nom, i ) {
			var part = total ? ( jeu.valeurs[ i ] / total * 100 ) : 0;
			var teinte = RAMPE[ i % RAMPE.length ];

			var seg = document.createElement( 'span' );
			seg.style.width = part + '%';
			seg.style.background = teinte;
			seg.title = nom + ' : ' + fr( jeu.valeurs[ i ] ) + ' (' + part.toFixed( 0 ) + ' %)';
			barre.appendChild( seg );

			var li = document.createElement( 'li' );
			var puce = document.createElement( 'i' );
			puce.style.background = teinte;
			li.appendChild( puce );
			li.appendChild( document.createTextNode( nom + ' ' ) );

			var b = document.createElement( 'b' );
			b.textContent = part.toFixed( 0 ) + ' %';
			li.appendChild( b );
			legende.appendChild( li );

			resume.push( nom + ' ' + part.toFixed( 0 ) + ' %' );
		} );

		barre.setAttribute( 'aria-label', resume.join( ', ' ) );
	} )();

	/* ── Vues tableau : l'équivalent chiffré de chaque graphique ── */

	Array.prototype.forEach.call( document.querySelectorAll( '.iam-vue-table' ), function ( bloc ) {
		var jeu = data[ bloc.getAttribute( 'data-source' ) ];

		if ( ! jeu || ! jeu.valeurs || ! jeu.valeurs.length ) {
			bloc.remove();
			return;
		}

		var table = document.createElement( 'table' );
		var thead = document.createElement( 'thead' );
		var trh = document.createElement( 'tr' );

		[ i18n.libelle || 'Libellé', i18n.leads || 'Leads' ].forEach( function ( titre ) {
			var th = document.createElement( 'th' );
			th.textContent = titre;
			trh.appendChild( th );
		} );

		thead.appendChild( trh );
		table.appendChild( thead );

		var tbody = document.createElement( 'tbody' );

		jeu.labels.forEach( function ( nom, i ) {
			var tr = document.createElement( 'tr' );
			var td1 = document.createElement( 'td' );
			var td2 = document.createElement( 'td' );

			td1.textContent = nom;
			td2.textContent = fr( jeu.valeurs[ i ] );

			tr.appendChild( td1 );
			tr.appendChild( td2 );
			tbody.appendChild( tr );
		} );

		table.appendChild( tbody );
		bloc.appendChild( table );
	} );

} )();
