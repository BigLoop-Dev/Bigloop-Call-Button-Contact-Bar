/**
 * Minimal router over real WordPress admin pages.
 *
 * Each route maps to a submenu page URL (admin.php?page=…). Extra parameters (e.g. widget=12) live in the
 * query string. Navigation uses pushState so screens switch instantly, while reloads/bookmarks still land on
 * the right WordPress page and menu highlighting stays correct.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { getQueryArg, getQueryArgs, addQueryArgs } from '@wordpress/url';

export function routeFromLocation( pages, href ) {
	const slug = getQueryArg( href, 'page' );
	const match = Object.entries( pages ).find( ( [ , p ] ) => p.slug === slug );
	return match ? match[ 0 ] : 'overview';
}

export function paramsFromLocation( href ) {
	const args = { ...getQueryArgs( href ) };
	delete args.page;
	return args;
}

export function urlFor( pages, route, params = {} ) {
	const page = pages[ route ];
	return page ? addQueryArgs( page.url, params ) : '';
}

/**
 * Updates the WordPress admin submenu "current" state to match the route.
 *
 * @param {Object} page Page config ({ slug }).
 */
export function highlightMenu( page ) {
	const menu = document.querySelector( '#toplevel_page_bigloop-contact' );
	if ( ! menu || ! page ) {
		return;
	}
	menu.querySelectorAll( '.wp-submenu li' ).forEach( ( li ) => {
		const a = li.querySelector( 'a' );
		const isCurrent = !! a && getQueryArg( a.href, 'page' ) === page.slug;
		li.classList.toggle( 'current', isCurrent );
		if ( a ) {
			a.classList.toggle( 'current', isCurrent );
			if ( isCurrent ) {
				a.setAttribute( 'aria-current', 'page' );
			} else {
				a.removeAttribute( 'aria-current' );
			}
		}
	} );
}

/**
 * Router hook.
 *
 * @param {Object} pages        Route → page config.
 * @param {string} initialRoute Route from the server.
 * @return {{route: string, params: Object, navigate: Function}} Router API.
 */
export function useRouter( pages, initialRoute ) {
	const [ state, setState ] = useState( () => ( {
		route: pages[ initialRoute ] ? initialRoute : 'overview',
		params: paramsFromLocation( window.location.href ),
	} ) );

	useEffect( () => {
		const onPop = () =>
			setState( {
				route: routeFromLocation( pages, window.location.href ),
				params: paramsFromLocation( window.location.href ),
			} );
		window.addEventListener( 'popstate', onPop );
		return () => window.removeEventListener( 'popstate', onPop );
	}, [ pages ] );

	useEffect( () => {
		const page = pages[ state.route ];
		highlightMenu( page );
		if ( page ) {
			document.title = document.title.replace( /^[^‹]*‹/, `${ page.title } ‹` );
		}
	}, [ state.route, pages ] );

	const navigate = useCallback(
		( next, params = {}, { replace = false } = {} ) => {
			if ( ! pages[ next ] ) {
				return;
			}
			const url = urlFor( pages, next, params );
			if ( url === window.location.href ) {
				return;
			}
			window.history[ replace ? 'replaceState' : 'pushState' ]( { route: next }, '', url );
			setState( { route: next, params } );
			if ( ! replace ) {
				window.scrollTo( 0, 0 );
			}
		},
		[ pages ]
	);

	return { route: state.route, params: state.params, navigate };
}

/**
 * Click handler for in-app links: lets modified clicks (new tab etc.) behave normally.
 *
 * @param {Function} navigate Navigate fn.
 * @param {string}   to       Route.
 * @param {Object}   [params] Query params.
 * @return {Function} onClick handler.
 */
export function linkHandler( navigate, to, params ) {
	return ( event ) => {
		if ( event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey ) {
			return;
		}
		event.preventDefault();
		navigate( to, params );
	};
}
