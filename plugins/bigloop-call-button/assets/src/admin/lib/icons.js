/**
 * Loads the plugin icon library (assets/icons/icons.json) once and renders icons.
 * The SVG markup is generated and sanitised at build time (tools/build-icons.mjs) and served from the plugin itself.
 */
import { useEffect, useState } from '@wordpress/element';

let promise = null;

export function loadIcons() {
	if ( ! promise ) {
		const url = window.blcbAdmin && window.blcbAdmin.iconsUrl;
		promise = url
			? window.fetch( url, { credentials: 'same-origin' } ).then( ( r ) => {
					if ( ! r.ok ) {
						throw new Error( 'Icon library unavailable' );
					}
					return r.json();
			  } )
			: Promise.resolve( { icons: {}, categories: [] } );
		promise.catch( () => {
			promise = null;
		} );
	}
	return promise;
}

export function useIcons() {
	const [ lib, setLib ] = useState( null );
	useEffect( () => {
		let live = true;
		loadIcons()
			.then( ( data ) => live && setLib( data ) )
			.catch( () => live && setLib( { icons: {}, categories: [] } ) );
		return () => {
			live = false;
		};
	}, [] );
	return lib;
}

/**
 * Renders a library icon by id.
 *
 * @param {Object} props
 * @param {Object} props.lib  Icon library.
 * @param {string} props.id   Icon id.
 * @param {number} [props.size] Pixel size.
 */
export function LibIcon( { lib, id, size = 20, className } ) {
	const svg = lib && lib.icons && lib.icons[ id ] ? lib.icons[ id ].svg : '';
	return (
		<span
			className={ `blcb-libicon${ className ? ' ' + className : '' }` }
			style={ { width: size, height: size } }
			aria-hidden="true"
			// eslint-disable-next-line react/no-danger -- trusted, build-time-sanitised local SVG.
			dangerouslySetInnerHTML={ { __html: svg } }
		/>
	);
}
