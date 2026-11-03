import { relativeTime, actionTypeLabel, formatNumber } from '../../assets/src/admin/lib/labels';
import { routeFromLocation } from '../../assets/src/admin/lib/router';

describe( 'relativeTime', () => {
	const now = Date.parse( '2026-11-01T12:00:00Z' );
	it( 'formats minutes and days relative to now (UTC input)', () => {
		expect( relativeTime( '2026-11-01 11:55:00', now ) ).toMatch( /5 minutes ago/ );
		expect( relativeTime( '2026-10-30 12:00:00', now ) ).toMatch( /2 days ago/ );
	} );
	it( 'returns empty string for invalid input', () => {
		expect( relativeTime( 'not a date', now ) ).toBe( '' );
	} );
} );

describe( 'labels', () => {
	it( 'maps known action types and passes unknown through', () => {
		expect( actionTypeLabel( 'whatsapp' ) ).toBe( 'WhatsApp' );
		expect( actionTypeLabel( 'telegram' ) ).toBe( 'telegram' );
	} );
	it( 'formats numbers with grouping', () => {
		expect( formatNumber( 1234567 ) ).toMatch( /1.?234.?567/ );
		expect( formatNumber( undefined ) ).toBe( '0' );
	} );
} );

describe( 'routeFromLocation', () => {
	const pages = {
		overview: { slug: 'bigloop-contact' },
		settings: { slug: 'bigloop-contact-settings' },
	};
	it( 'resolves the route from the page query arg', () => {
		expect( routeFromLocation( pages, 'https://x.test/wp-admin/admin.php?page=bigloop-contact-settings' ) ).toBe( 'settings' );
	} );
	it( 'falls back to overview', () => {
		expect( routeFromLocation( pages, 'https://x.test/wp-admin/admin.php?page=unknown' ) ).toBe( 'overview' );
	} );
} );
