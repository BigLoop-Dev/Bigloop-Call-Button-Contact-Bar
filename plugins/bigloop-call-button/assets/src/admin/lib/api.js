/**
 * REST helpers for the plugin namespace. Nonce and root URL middleware are installed by WordPress core.
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

const ns = () => ( window.blcbAdmin && window.blcbAdmin.restNamespace ) || 'bigloop-call-button/v1';

export function get( path, query ) {
	return apiFetch( { path: addQueryArgs( `/${ ns() }${ path }`, query || {} ) } );
}

export function put( path, data ) {
	return apiFetch( { path: `/${ ns() }${ path }`, method: 'PUT', data } );
}

export function post( path, data ) {
	return apiFetch( { path: `/${ ns() }${ path }`, method: 'POST', data } );
}

export function del( path ) {
	return apiFetch( { path: `/${ ns() }${ path }`, method: 'DELETE' } );
}

/**
 * Normalises an apiFetch error into { message, fieldErrors }.
 *
 * @param {*} error Thrown value.
 * @return {{message: string, fieldErrors: Object}} Normalised error.
 */
export function parseError( error ) {
	const message = ( error && error.message ) || 'Something went wrong. Please try again.';
	const fieldErrors = ( error && error.data && error.data.errors ) || {};
	return { message, fieldErrors };
}
