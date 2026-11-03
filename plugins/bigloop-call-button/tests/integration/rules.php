<?php
/**
 * Integration test for scheduling and display rules (Phase 5). Needs the install served over HTTP.
 * Usage: php tests/integration/rules.php /path/to/wordpress http://127.0.0.1:8890
 *
 * @package Bigloop\CallButton
 */
// phpcs:disable
$wp_dir = $argv[1] ?? '';
$url    = rtrim( $argv[2] ?? '', '/' );
$_SERVER['HTTP_HOST']   = parse_url( $url, PHP_URL_HOST );
$_SERVER['REQUEST_URI'] = '/';
require $wp_dir . '/wp-load.php';
$fail = 0; $pass = 0;
function ok( $c, $l, $d = '' ) { global $fail, $pass; if ( $c ) { $pass++; echo "  ✓ $l\n"; } else { $fail++; echo "  ✗ $l" . ( $d ? " — $d" : '' ) . "\n"; } }
function r( $m, $p, $b = null ) { $q = new WP_REST_Request( $m, '/bigloop-call-button/v1' . $p ); if ( null !== $b ) { $q->set_header( 'content-type', 'application/json' ); $q->set_body( wp_json_encode( $b ) ); } $res = rest_do_request( $q ); return array( $res->get_status(), json_decode( wp_json_encode( rest_get_server()->response_to_data( $res, false ) ), true ) ); }
function fetch( $u ) { return (string) @file_get_contents( $u, false, stream_context_create( array( 'http' => array( 'timeout' => 20, 'ignore_errors' => true ) ) ) ); }

foreach ( get_posts( array( 'post_type' => array( 'blcb_widget', 'blcb_action', 'blcb_schedule' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
wp_set_current_user( 1 );
do_action( 'rest_api_init', rest_get_server() );

echo "Schedules\n";
$all_week = array_fill_keys( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ), array( array( '00:00', '24:00' ) ) );
$never    = array_fill_keys( array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ), array() );
list( $s, $open ) = r( 'POST', '/schedules', array( 'title' => 'Always open', 'config' => array( 'timezone' => 'UTC', 'weekly' => $all_week ) ) );
ok( 201 === $s && true === $open['summary']['open_now'], 'always-open schedule created and open now' );
list( $s, $closed ) = r( 'POST', '/schedules', array( 'title' => 'Closed', 'config' => array( 'timezone' => '', 'weekly' => $never ) ) );
ok( 201 === $s && false === $closed['summary']['open_now'] && null === $closed['summary']['next_change'], 'never-open schedule closed, no next change' );
ok( wp_timezone_string() === $closed['summary']['timezone'], 'empty timezone resolves to site timezone' );
list( $s, $bad ) = r( 'POST', '/schedules', array( 'title' => '', 'config' => array( 'timezone' => 'Nowhere/City', 'weekly' => array( 'mon' => array( array( '25:00', '26:00' ) ) ) ) ) );
ok( 400 === $s && isset( $bad['data']['errors']['title'], $bad['data']['errors']['timezone'], $bad['data']['errors']['weekly.mon.0'] ), 'invalid schedule rejected with field errors' );
list( $s, $pv ) = r( 'POST', '/schedules/preview', array( 'config' => array( 'timezone' => 'Europe/London' ) ) );
ok( 200 === $s && 7 === count( $pv['week'] ) && array( array( '09:00', '17:00' ) ) === $pv['week'][ array_search( 'Monday', array_column( $pv['week'], 'weekday' ), true ) ]['intervals'], 'draft preview lists the week (Mon 09–17 default)' );
list( $s, $meta ) = r( 'GET', '/schedules/meta' );
ok( in_array( 'Europe/London', $meta['timezones'], true ), 'timezone list' );

echo "Actions & widgets\n";
list( , $phone ) = r( 'POST', '/actions', array( 'type' => 'phone', 'label' => 'Call', 'value' => array( 'number' => '+15550001111' ) ) );
list( , $wa )    = r( 'POST', '/actions', array( 'type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => array( 'number' => '+15550002222' ) ) );
list( , $mail )  = r( 'POST', '/actions', array( 'type' => 'email', 'label' => 'Email', 'value' => array( 'address' => 'a@b.co' ) ) );
$refs = array( array( 'id' => $phone['id'], 'when' => 'open' ), array( 'id' => $wa['id'], 'when' => 'closed' ), array( 'id' => $mail['id'], 'when' => 'always' ) );

list( $s, $w_open ) = r( 'POST', '/widgets', array( 'title' => 'Open swap', 'enabled' => true, 'config' => array( 'type' => 'menu', 'actions' => $refs, 'schedule' => array( 'enabled' => true, 'schedule_id' => $open['id'], 'when_closed' => 'swap' ) ) ) );
ok( 201 === $s, 'widget following open schedule created', wp_json_encode( $w_open['data']['errors'] ?? '' ) );
list( $s, $w_closed ) = r( 'POST', '/widgets', array( 'title' => 'Closed swap', 'enabled' => true, 'config' => array( 'type' => 'bar', 'actions' => $refs, 'schedule' => array( 'enabled' => true, 'schedule_id' => $closed['id'] ) ) ) );
list( $s, $w_hide ) = r( 'POST', '/widgets', array( 'title' => 'Closed hide', 'enabled' => true, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $phone['id'] ) ), 'schedule' => array( 'enabled' => true, 'schedule_id' => $closed['id'], 'when_closed' => 'hide' ) ) ) );
list( $s, $err ) = r( 'POST', '/widgets', array( 'title' => 'Bad', 'config' => array( 'type' => 'menu', 'schedule' => array( 'enabled' => true, 'schedule_id' => 999999 ) ) ) );
ok( 400 === $s && isset( $err['data']['errors']['schedule.schedule_id'] ), 'missing schedule rejected' );

$about = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'About us', 'post_status' => 'publish', 'post_name' => 'about-us' ) );
$cat   = wp_insert_term( 'News ' . wp_rand(), 'category' );
$post  = wp_insert_post( array( 'post_type' => 'post', 'post_title' => 'Hello news', 'post_status' => 'publish', 'post_category' => array( $cat['term_id'] ) ) );

list( $s, $w_page ) = r( 'POST', '/widgets', array( 'title' => 'Only About', 'enabled' => true, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $mail['id'] ) ), 'rules' => array( 'match' => 'all', 'items' => array( array( 'type' => 'page', 'op' => 'is', 'value' => array( $about ) ) ) ) ) ) );
ok( 201 === $s, 'page-rule widget created' );
list( $s, $w_cat ) = r( 'POST', '/widgets', array( 'title' => 'News posts', 'enabled' => true, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $wa['id'] ) ), 'rules' => array( 'match' => 'any', 'items' => array( array( 'type' => 'taxonomy', 'op' => 'is', 'value' => array( 'taxonomy' => 'category', 'terms' => array( $cat['term_id'] ) ) ), array( 'type' => 'url', 'op' => 'contains', 'value' => 'promo' ) ) ) ) ) );
list( $s, $w_utm ) = r( 'POST', '/widgets', array( 'title' => 'UTM only', 'enabled' => true, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $phone['id'] ) ), 'rules' => array( 'items' => array( array( 'type' => 'url_param', 'op' => 'equals', 'value' => array( 'key' => 'utm_source', 'value' => 'google' ) ), array( 'type' => 'login', 'op' => 'is', 'value' => 'logged_out' ) ) ), 'reveal' => array( 'scroll' => 50, 'scroll_unit' => '%' ) ) ) );
list( $s, $badrule ) = r( 'POST', '/widgets', array( 'title' => 'Bad rule', 'config' => array( 'type' => 'menu', 'rules' => array( 'items' => array( array( 'type' => 'page', 'op' => 'is', 'value' => array() ), array( 'type' => 'nope', 'op' => 'is' ), array( 'type' => 'url', 'op' => 'is', 'value' => 'x' ) ) ) ) ) );
ok( 400 === $s && isset( $badrule['data']['errors']['rules.items.0'], $badrule['data']['errors']['rules.items.1'], $badrule['data']['errors']['rules.items.2'] ), 'invalid rules rejected per item' );

echo "Front end: schedules\n";
$home = fetch( $url . '/' );
preg_match( '#<div id="blcb-w-' . $w_open['id'] . '"[^>]*>#', $home, $m );
ok( isset( $m[0] ) && false !== strpos( $m[0], 'data-blcb-sched=' ) && false !== strpos( $m[0], 'data-blcb-state="open"' ) && false === strpos( $m[0], ' hidden' ), 'open widget: windows embedded, state open, visible' );
ok( (bool) preg_match( '#<li class="blcb-menu__item" data-blcb-when="open">[^\n]*?tel:\+15550001111#', $home ), 'open: "open" action visible' );
ok( (bool) preg_match( '#<li class="blcb-menu__item" data-blcb-when="closed" hidden>#', $home ), 'open: "closed" action hidden' );
preg_match( '#<nav id="blcb-w-' . $w_closed['id'] . '"[^>]*>#', $home, $m );
ok( isset( $m[0] ) && false !== strpos( $m[0], 'data-blcb-state="closed"' ), 'closed bar: state closed' );
ok( (bool) preg_match( '#data-blcb-when="open" hidden><a class="blcb-bar__link"[^>]*tel:\+15550001111#', $home ), 'closed: phone hidden (swap)' );
ok( (bool) preg_match( '#<li class="blcb-bar__item"[^>]*data-blcb-when="closed"><a class="blcb-bar__link"[^>]*wa\.me#', $home ), 'closed: WhatsApp shown (swap)' );
preg_match( '#<div id="blcb-w-' . $w_hide['id'] . '"[^>]*>#', $home, $m );
ok( isset( $m[0] ) && false !== strpos( $m[0], 'data-blcb-hide-closed="1"' ) && false !== strpos( $m[0], ' hidden' ), 'hide-when-closed widget rendered hidden (browser re-checks)' );
ok( false !== strpos( $home, 'body:has(#blcb-w-' . $w_closed['id'] . ':not([hidden]))' ), 'bar spacing only while bar shown' );

echo "Front end: display rules\n";
ok( false === strpos( $home, 'id="blcb-w-' . $w_page['id'] . '"' ), 'page rule: hidden on home' );
$about_html = fetch( get_permalink( $about ) );
ok( false !== strpos( $about_html, 'id="blcb-w-' . $w_page['id'] . '"' ), 'page rule: shown on About' );
ok( false === strpos( $home, 'id="blcb-w-' . $w_cat['id'] . '"' ), 'any-match: hidden on home' );
ok( false !== strpos( fetch( get_permalink( $post ) ), 'id="blcb-w-' . $w_cat['id'] . '"' ), 'any-match: shown on post in category' );
update_option( 'permalink_structure', '/%postname%/' ); flush_rewrite_rules( false );
$promo = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Spring promo', 'post_status' => 'publish', 'post_name' => 'spring-promo' ) );
ok( false !== strpos( fetch( $url . '/spring-promo/' ), 'id="blcb-w-' . $w_cat['id'] . '"' ), 'any-match: URL path contains "promo"' );
ok( false === strpos( fetch( $url . '/about-us/' ), 'id="blcb-w-' . $w_cat['id'] . '"' ), 'any-match: hidden on unrelated path' );
wp_delete_post( $promo, true ); update_option( 'permalink_structure', '' ); flush_rewrite_rules( false );
preg_match( '#<div id="blcb-w-' . $w_utm['id'] . '"[^>]*>#', $home, $m );
ok( isset( $m[0] ) && false !== strpos( $m[0], 'data-blcb-client=' ) && false !== strpos( $m[0], 'data-blcb-reveal=' ) && false !== strpos( $m[0], ' hidden' ), 'client rule + reveal: rendered hidden with data for the browser' );
ok( false === strpos( $m[0] ?? '', 'logged_out' ), 'server-decided rule (logged out) not sent to the browser' );

echo "Geo\n";
list( $s, $geo ) = r( 'GET', '/geo' );
ok( 200 === $s && null === $geo['country'], 'geo disabled: no country' );
$settings = bigloop_call_button()->container()->get( \Bigloop\CallButton\Settings\Settings::class );
$settings->update( array( 'geo' => array( 'enabled' => true, 'source' => 'header', 'header' => 'GEOIP_COUNTRY_CODE' ) ) );
$geo_svc = new \Bigloop\CallButton\Geo\GeoService( $settings );
ok( 'DE' === $geo_svc->country( array( 'GEOIP_COUNTRY_CODE' => 'de' ) ), 'header source reads country' );
ok( null === $geo_svc->country( array( 'GEOIP_COUNTRY_CODE' => 'not a code' ) ), 'invalid value ignored' );
$settings->update( array( 'geo' => array( 'header' => 'DB_PASSWORD' ) ) );
ok( null === ( new \Bigloop\CallButton\Geo\GeoService( $settings ) )->country( array( 'DB_PASSWORD' => 'AB' ) ), 'non-geo server variables are never read' );
$settings->update( array( 'geo' => array( 'enabled' => true, 'source' => 'auto', 'header' => 'GEOIP_COUNTRY_CODE' ) ) );
ok( 'GB' === ( new \Bigloop\CallButton\Geo\GeoService( $settings ) )->country( array( 'HTTP_CF_IPCOUNTRY' => 'GB' ) ), 'auto source reads Cloudflare header' );
ok( null === ( new \Bigloop\CallButton\Geo\GeoService( $settings ) )->country( array( 'HTTP_CF_IPCOUNTRY' => 'XX' ) ), 'Cloudflare unknown marker ignored' );
$settings->update( array( 'geo' => array( 'enabled' => false ) ) );

echo "Schedule deletion\n";
list( $s, $del ) = r( 'DELETE', '/schedules/' . $closed['id'] );
ok( 200 === $s && 2 === $del['widgets_affected'], 'deleting a schedule detaches its widgets (2)' );
list( , $wc ) = r( 'GET', '/widgets/' . $w_closed['id'] );
ok( false === $wc['config']['schedule']['enabled'], 'detached widget no longer scheduled' );

wp_delete_post( $about, true ); wp_delete_post( $post, true ); wp_delete_term( $cat['term_id'], 'category' );
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
