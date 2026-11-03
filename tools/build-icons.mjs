#!/usr/bin/env node
/**
 * Builds the plugin's curated icon library from Lucide (ISC) and Simple Icons (CC0).
 *
 * Output: plugins/bigloop-call-button/assets/icons/icons.json
 *   { version, icons: { [id]: { label, category, svg, source } }, categories: [...] }
 *
 * SVGs are normalised to a 24×24 viewBox, use currentColor, and carry no attributes beyond a strict allow-list.
 * Run: node tools/build-icons.mjs   (re-run when the curated list changes)
 */
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const lucideDir = join( dirname( require.resolve( 'lucide-static/package.json' ) ), 'icons' );
const si = require( 'simple-icons' );

// [ id, lucide file name, label, category ]
const LUCIDE = [
	[ 'phone', 'phone', 'Phone', 'phone' ],
	[ 'phone-call', 'phone-call', 'Phone call', 'phone' ],
	[ 'phone-incoming', 'phone-incoming', 'Incoming call', 'phone' ],
	[ 'phone-forwarded', 'phone-forwarded', 'Call forwarding', 'phone' ],
	[ 'headset', 'headset', 'Headset', 'phone' ],
	[ 'smartphone', 'smartphone', 'Mobile phone', 'mobile' ],
	[ 'tablet-smartphone', 'tablet-smartphone', 'Devices', 'mobile' ],
	[ 'message-circle', 'message-circle', 'Chat bubble', 'chat' ],
	[ 'message-circle-more', 'message-circle-more', 'Chat bubble (typing)', 'chat' ],
	[ 'message-square', 'message-square', 'Message', 'sms' ],
	[ 'message-square-text', 'message-square-text', 'Text message', 'sms' ],
	[ 'messages-square', 'messages-square', 'Conversation', 'chat' ],
	[ 'bot', 'bot', 'Chat bot', 'chat' ],
	[ 'send', 'send', 'Send', 'chat' ],
	[ 'mail', 'mail', 'Email', 'email' ],
	[ 'mail-open', 'mail-open', 'Open email', 'email' ],
	[ 'at-sign', 'at-sign', 'At sign', 'email' ],
	[ 'inbox', 'inbox', 'Inbox', 'email' ],
	[ 'map-pin', 'map-pin', 'Location pin', 'location' ],
	[ 'map', 'map', 'Map', 'location' ],
	[ 'map-pinned', 'map-pinned', 'Pinned map', 'location' ],
	[ 'navigation', 'navigation', 'Navigate', 'directions' ],
	[ 'route', 'route', 'Route', 'directions' ],
	[ 'car', 'car', 'Car', 'directions' ],
	[ 'compass', 'compass', 'Compass', 'directions' ],
	[ 'calendar', 'calendar', 'Calendar', 'calendar' ],
	[ 'calendar-check', 'calendar-check', 'Calendar check', 'appointment' ],
	[ 'calendar-clock', 'calendar-clock', 'Appointment time', 'appointment' ],
	[ 'calendar-plus', 'calendar-plus', 'Book appointment', 'appointment' ],
	[ 'clock', 'clock', 'Clock', 'calendar' ],
	[ 'briefcase', 'briefcase', 'Briefcase', 'business' ],
	[ 'building', 'building-2', 'Building', 'business' ],
	[ 'store', 'store', 'Store', 'business' ],
	[ 'shopping-bag', 'shopping-bag', 'Shopping bag', 'business' ],
	[ 'shopping-cart', 'shopping-cart', 'Shopping cart', 'business' ],
	[ 'stethoscope', 'stethoscope', 'Medical', 'business' ],
	[ 'utensils', 'utensils', 'Restaurant', 'business' ],
	[ 'house', 'house', 'Home', 'business' ],
	[ 'wrench', 'wrench', 'Service', 'business' ],
	[ 'user', 'user', 'Person', 'business' ],
	[ 'users', 'users', 'Team', 'business' ],
	[ 'info', 'info', 'Information', 'business' ],
	[ 'circle-help', 'circle-help', 'Help', 'business' ],
	[ 'star', 'star', 'Star', 'business' ],
	[ 'heart', 'heart', 'Heart', 'business' ],
	[ 'gift', 'gift', 'Gift', 'business' ],
	[ 'tag', 'tag', 'Tag', 'business' ],
	[ 'globe', 'globe', 'Website', 'custom' ],
	[ 'link', 'link', 'Link', 'custom' ],
	[ 'external-link', 'external-link', 'External link', 'custom' ],
	[ 'file-text', 'file-text', 'Document', 'custom' ],
	[ 'download', 'download', 'Download', 'custom' ],
	[ 'arrow-up', 'arrow-up', 'Arrow up', 'custom' ],
	[ 'zap', 'zap', 'Lightning', 'custom' ],
	[ 'sparkles', 'sparkles', 'Sparkles', 'custom' ],
	[ 'x', 'x', 'Close', 'ui' ],
	[ 'plus', 'plus', 'Plus', 'ui' ],
	[ 'menu', 'menu', 'Menu', 'ui' ],
	[ 'chevron-up', 'chevron-up', 'Chevron up', 'ui' ],
];

// [ id, simple-icons export, category ]
const BRANDS = [
	[ 'whatsapp', 'siWhatsapp', 'whatsapp' ],
	[ 'telegram', 'siTelegram', 'social' ],
	[ 'messenger', 'siMessenger', 'social' ],
	[ 'viber', 'siViber', 'social' ],
	[ 'signal', 'siSignal', 'social' ],
	[ 'line', 'siLine', 'social' ],
	[ 'wechat', 'siWechat', 'social' ],
	[ 'kakaotalk', 'siKakaotalk', 'social' ],
	[ 'zalo', 'siZalo', 'social' ],
	[ 'discord', 'siDiscord', 'social' ],
	[ 'facebook', 'siFacebook', 'social' ],
	[ 'instagram', 'siInstagram', 'social' ],
	[ 'x-twitter', 'siX', 'social' ],
	[ 'threads', 'siThreads', 'social' ],
	[ 'youtube', 'siYoutube', 'social' ],
	[ 'tiktok', 'siTiktok', 'social' ],
	[ 'pinterest', 'siPinterest', 'social' ],
	[ 'snapchat', 'siSnapchat', 'social' ],
	[ 'google-maps', 'siGooglemaps', 'location' ],
	[ 'google-calendar', 'siGooglecalendar', 'appointment' ],
	[ 'calendly', 'siCalendly', 'appointment' ],
];

const CATEGORIES = [
	[ 'phone', 'Phone' ], [ 'mobile', 'Mobile' ], [ 'whatsapp', 'WhatsApp' ], [ 'sms', 'SMS' ], [ 'email', 'Email' ],
	[ 'location', 'Location' ], [ 'directions', 'Directions' ], [ 'calendar', 'Calendar' ], [ 'appointment', 'Appointment' ],
	[ 'chat', 'Chat' ], [ 'social', 'Social media' ], [ 'business', 'Business' ], [ 'custom', 'Custom' ], [ 'ui', 'Interface' ],
];

/** Extracts inner elements of a Lucide SVG and rebuilds a clean root element. */
function lucideSvg( file ) {
	const raw = readFileSync( join( lucideDir, `${ file }.svg` ), 'utf8' );
	const inner = raw
		.replace( /<!--[\s\S]*?-->/g, '' )
		.replace( /^[\s\S]*?<svg[^>]*>/, '' )
		.replace( /<\/svg>[\s\S]*$/, '' )
		.replace( /\s+/g, ' ' )
		.trim();
	if ( /<(script|foreignObject|style)|on\w+=|href=/i.test( inner ) ) {
		throw new Error( `Unexpected content in ${ file }` );
	}
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ inner }</svg>`;
}

function brandSvg( icon ) {
	if ( ! /^[MmLlHhVvCcSsQqTtAaZz0-9.,\s\-e]+$/.test( icon.path ) ) {
		throw new Error( `Unexpected path data in ${ icon.title }` );
	}
	return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="${ icon.path }"/></svg>`;
}

const icons = {};
for ( const [ id, file, label, category ] of LUCIDE ) {
	icons[ id ] = { label, category, svg: lucideSvg( file ), source: 'lucide' };
}
for ( const [ id, key, category ] of BRANDS ) {
	const icon = si[ key ];
	if ( ! icon ) {
		throw new Error( `Simple Icons export missing: ${ key }` );
	}
	icons[ id ] = { label: icon.title, category, svg: brandSvg( icon ), source: 'simple-icons', brandColor: `#${ icon.hex }` };
}

const lucideVersion = JSON.parse( readFileSync( join( lucideDir, '..', 'package.json' ), 'utf8' ) ).version;
const siVersion = JSON.parse( readFileSync( join( root, 'node_modules/simple-icons/package.json' ), 'utf8' ) ).version;
const out = {
	version: `lucide-${ lucideVersion }+simple-icons-${ siVersion }`,
	categories: CATEGORIES.map( ( [ id, label ] ) => ( { id, label } ) ),
	icons,
};

const target = join( root, 'plugins/bigloop-call-button/assets/icons/icons.json' );
mkdirSync( dirname( target ), { recursive: true } );
writeFileSync( target, JSON.stringify( out ) + '\n' );
console.log( `Wrote ${ Object.keys( icons ).length } icons → ${ target }` );
