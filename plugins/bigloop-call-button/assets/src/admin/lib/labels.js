import { __ } from '@wordpress/i18n';

/**
 * Human labels for action types (shared by dashboard and analytics).
 *
 * @param {string} type Action type id.
 * @return {string} Label.
 */
export function actionTypeLabel( type ) {
	const map = {
		phone: __( 'Phone call', 'bigloop-call-button' ),
		sms: __( 'SMS', 'bigloop-call-button' ),
		whatsapp: __( 'WhatsApp', 'bigloop-call-button' ),
		email: __( 'Email', 'bigloop-call-button' ),
		map: __( 'Map location', 'bigloop-call-button' ),
		directions: __( 'Directions', 'bigloop-call-button' ),
		url: __( 'Website link', 'bigloop-call-button' ),
		booking: __( 'Appointment booking', 'bigloop-call-button' ),
		custom: __( 'Custom action', 'bigloop-call-button' ),
	};
	return map[ type ] || type;
}

export function deviceLabel( device ) {
	return (
		{
			1: __( 'Desktop', 'bigloop-call-button' ),
			2: __( 'Tablet', 'bigloop-call-button' ),
			3: __( 'Mobile', 'bigloop-call-button' ),
		}[ device ] || __( 'Unknown device', 'bigloop-call-button' )
	);
}

/**
 * Formats a UTC MySQL datetime as a relative time ("5 minutes ago").
 *
 * @param {string} mysqlUtc "YYYY-MM-DD HH:MM:SS" in UTC.
 * @param {number} [now]    Current time (ms), for tests.
 * @return {string} Relative time.
 */
export function relativeTime( mysqlUtc, now = Date.now() ) {
	const then = Date.parse( mysqlUtc.replace( ' ', 'T' ) + 'Z' );
	if ( Number.isNaN( then ) ) {
		return '';
	}
	const seconds = Math.round( ( then - now ) / 1000 );
	const abs = Math.abs( seconds );
	const units = [
		[ 'year', 31536000 ],
		[ 'month', 2592000 ],
		[ 'week', 604800 ],
		[ 'day', 86400 ],
		[ 'hour', 3600 ],
		[ 'minute', 60 ],
	];
	const rtf = new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } );
	for ( const [ unit, size ] of units ) {
		if ( abs >= size ) {
			return rtf.format( Math.round( seconds / size ), unit );
		}
	}
	return rtf.format( 0, 'second' );
}

export function formatNumber( n ) {
	return new Intl.NumberFormat().format( n || 0 );
}
