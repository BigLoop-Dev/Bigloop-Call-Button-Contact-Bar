/**
 * Bigloop Contact front-end runtime (no dependencies).
 *
 * - Expandable menus use native <details>; this script adds Escape-to-close (returning focus to the toggle),
 *   closing on outside click, and keeps only one menu open at a time.
 * - Dispatches `bigloop:contact-click` on document for every action click: detail = { widget, action, type, href, label },
 *   sends the anonymous statistics beacon, and pushes a Google Analytics event when the site already uses gtag or GTM.
 */
import './frontend.scss';

( function () {
	const MENU = '.blcb-menu';

	// ---------- Gates: schedule, browser-side rules, reveal ----------
	const parse = ( el, attr ) => {
		try {
			return JSON.parse( el.getAttribute( attr ) || 'null' );
		} catch ( e ) {
			return null;
		}
	};

	let countryPromise = null;
	function country( url ) {
		if ( ! countryPromise ) {
			let cached = null;
			try {
				cached = window.sessionStorage.getItem( 'blcb_country' );
			} catch ( e ) {}
			countryPromise = cached
				? Promise.resolve( cached === '-' ? null : cached )
				: window
						.fetch( url, { credentials: 'omit', cache: 'no-store' } )
						.then( ( r ) => ( r.ok ? r.json() : { country: null } ) )
						.then( ( d ) => {
							try {
								window.sessionStorage.setItem( 'blcb_country', d.country || '-' );
							} catch ( e ) {}
							return d.country || null;
						} )
						.catch( () => null );
		}
		return countryPromise;
	}

	function ruleMatches( rule, code ) {
		if ( rule.type === 'url_param' ) {
			const params = new URLSearchParams( window.location.search );
			const has = params.has( rule.value.key );
			if ( rule.op === 'exists' ) {
				return has;
			}
			if ( rule.op === 'not_exists' ) {
				return ! has;
			}
			return has && params.get( rule.value.key ) === rule.value.value;
		}
		if ( rule.type === 'country' ) {
			const hit = !! code && rule.value.indexOf( code ) !== -1;
			// Unknown country never matches "is one of" and never excludes for "is not one of".
			return rule.op === 'not_in' ? ! hit : hit;
		}
		return false;
	}

	function clientOk( el ) {
		const spec = parse( el, 'data-blcb-client' );
		if ( ! spec ) {
			return Promise.resolve( true );
		}
		const needsGeo = spec.items.some( ( r ) => r.type === 'country' );
		const geoUrl = el.getAttribute( 'data-blcb-geo' );
		return ( needsGeo && geoUrl ? country( geoUrl ) : Promise.resolve( null ) ).then( ( code ) => {
			const results = spec.items.map( ( r ) => ruleMatches( r, code ) );
			return spec.match === 'any' ? results.some( Boolean ) : results.every( Boolean );
		} );
	}

	function scheduleOk( el ) {
		const windows = parse( el, 'data-blcb-sched' );
		if ( ! windows ) {
			return true;
		}
		const now = Date.now() / 1000;
		const open = windows.some( ( w ) => now >= w[ 0 ] && now < w[ 1 ] );
		el.setAttribute( 'data-blcb-state', open ? 'open' : 'closed' );
		let visible = 0;
		el.querySelectorAll( '[data-blcb-when]' ).forEach( ( item ) => {
			const show = ( item.getAttribute( 'data-blcb-when' ) === 'open' ) === open;
			item.hidden = ! show;
			visible += show ? 1 : 0;
		} );
		visible += el.querySelectorAll( '[data-blcb-action]' ).length - el.querySelectorAll( '[data-blcb-when] [data-blcb-action]' ).length;
		if ( ! open && el.hasAttribute( 'data-blcb-hide-closed' ) ) {
			return false;
		}
		return visible > 0;
	}

	const revealed = new WeakSet();
	function revealOk( el ) {
		const spec = parse( el, 'data-blcb-reveal' );
		if ( ! spec || revealed.has( el ) ) {
			return true;
		}
		const scrolled = spec.unit === '%'
			? ( window.scrollY / Math.max( 1, document.documentElement.scrollHeight - window.innerHeight ) ) * 100
			: window.scrollY;
		const waited = ( performance.now() / 1000 ) >= spec.delay;
		if ( scrolled >= spec.scroll && waited ) {
			revealed.add( el ); // Once shown, stay shown.
			return true;
		}
		return false;
	}

	const gated = Array.prototype.slice.call( document.querySelectorAll( '.blcb[data-blcb-sched], .blcb[data-blcb-client], .blcb[data-blcb-reveal]' ) );
	const clientState = new Map();

	function update() {
		gated.forEach( ( el ) => {
			const ok = scheduleOk( el ) && clientState.get( el ) !== false && revealOk( el );
			if ( el.hidden === ok ) {
				el.hidden = ! ok;
				if ( ! ok ) {
					const menu = el.querySelector( MENU + '[open]' );
					if ( menu ) {
						menu.open = false;
					}
				}
			}
		} );
	}

	if ( gated.length ) {
		gated.forEach( ( el ) => {
			if ( el.hasAttribute( 'data-blcb-client' ) ) {
				clientState.set( el, false );
				clientOk( el ).then( ( ok ) => {
					clientState.set( el, ok );
					update();
				} );
			}
		} );
		update();
		setInterval( update, 30000 );
		const needsScroll = gated.some( ( el ) => el.hasAttribute( 'data-blcb-reveal' ) );
		if ( needsScroll ) {
			let ticking = false;
			window.addEventListener(
				'scroll',
				() => {
					if ( ! ticking ) {
						ticking = true;
						window.requestAnimationFrame( () => {
							ticking = false;
							update();
						} );
					}
				},
				{ passive: true }
			);
			gated.forEach( ( el ) => {
				const spec = parse( el, 'data-blcb-reveal' );
				if ( spec && spec.delay ) {
					setTimeout( update, spec.delay * 1000 + 50 );
				}
			} );
		}
	}

	// ---------- Click reporting: local statistics beacon + Google Analytics ----------
	const config = window.blcbConfig || {};

	function deviceClass() {
		const w = window.innerWidth || document.documentElement.clientWidth;
		if ( w <= 767 ) {
			return 3;
		}
		return w <= 1024 ? 2 : 1;
	}

	function report( d ) {
		if ( ! d.widget || ! d.action ) {
			return;
		}
		if ( config.track ) {
			const payload = { w: d.widget, a: d.action, d: deviceClass(), o: config.obj || 0 };
			// Extensions (e.g. Pro attribution) may add flat key/value data to `extra`.
			const extra = {};
			try {
				document.dispatchEvent( new CustomEvent( 'bigloop:before-track', { detail: { click: d, extra } } ) );
			} catch ( e ) {}
			if ( Object.keys( extra ).length ) {
				payload.x = extra;
			}
			const body = JSON.stringify( payload );
			let sent = false;
			try {
				sent = !! ( navigator.sendBeacon && navigator.sendBeacon( config.track, new Blob( [ body ], { type: 'application/json' } ) ) );
			} catch ( e ) {}
			if ( ! sent && window.fetch ) {
				window.fetch( config.track, { method: 'POST', body, headers: { 'Content-Type': 'application/json' }, keepalive: true, credentials: 'omit' } ).catch( () => {} );
			}
		}
		// Only when the site already loads Google Analytics / Tag Manager; the plugin never loads it itself.
		if ( config.ga ) {
			const params = { widget_id: d.widget, action_id: d.action, action_type: d.type, link_text: d.label };
			if ( typeof window.gtag === 'function' ) {
				window.gtag( 'event', config.ga, params );
			} else if ( Array.isArray( window.dataLayer ) ) {
				window.dataLayer.push( Object.assign( { event: config.ga }, params ) );
			}
		}
	}

	function closeAll( except ) {
		document.querySelectorAll( MENU + '[open]' ).forEach( ( menu ) => {
			if ( menu !== except ) {
				menu.open = false;
			}
		} );
	}

	document.addEventListener(
		'toggle',
		( event ) => {
			const menu = event.target;
			if ( menu instanceof HTMLDetailsElement && menu.matches( MENU ) && menu.open ) {
				closeAll( menu );
			}
		},
		true
	);

	document.addEventListener( 'keydown', ( event ) => {
		if ( event.key !== 'Escape' ) {
			return;
		}
		const open = document.querySelector( MENU + '[open]' );
		if ( open ) {
			open.open = false;
			const summary = open.querySelector( 'summary' );
			if ( summary ) {
				summary.focus();
			}
		}
	} );

	document.addEventListener( 'click', ( event ) => {
		const target = event.target instanceof Element ? event.target : null;
		if ( ! target ) {
			return;
		}

		if ( ! target.closest( MENU ) ) {
			closeAll( null );
		}

		const link = target.closest( 'a[data-blcb-action]' );
		if ( ! link ) {
			return;
		}
		const detail = {
			widget: Number( link.getAttribute( 'data-blcb-widget' ) ) || 0,
			action: Number( link.getAttribute( 'data-blcb-action' ) ) || 0,
			type: link.getAttribute( 'data-blcb-type' ) || '',
			href: link.getAttribute( 'href' ) || '',
			label: ( link.getAttribute( 'aria-label' ) || link.textContent || '' ).trim().slice( 0, 100 ),
		};
		document.dispatchEvent( new CustomEvent( 'bigloop:contact-click', { detail } ) );
		report( detail );

		// Close the menu after choosing an action that stays on this page (tel:, mailto:, etc.).
		const menu = link.closest( MENU );
		if ( menu && link.getAttribute( 'target' ) !== '_blank' ) {
			setTimeout( () => ( menu.open = false ), 0 );
		}
	} );
} )();
