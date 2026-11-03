<?php
/**
 * Integration test for analytics (Phase 6). Needs the install served over HTTP.
 * Usage: php tests/integration/analytics.php /path/to/wordpress http://127.0.0.1:8890
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
function r( $m, $p, $b = null, $q = array() ) { $req = new WP_REST_Request( $m, '/bigloop-call-button/v1' . $p ); foreach ( $q as $k => $v ) { $req->set_param( $k, $v ); } if ( null !== $b ) { $req->set_header( 'content-type', 'application/json' ); $req->set_body( wp_json_encode( $b ) ); } $res = rest_do_request( $req ); return array( $res->get_status(), json_decode( wp_json_encode( rest_get_server()->response_to_data( $res, false ) ), true ), $res ); }
function beacon( $url, $body, $type = 'application/json', $ip_hint = '' ) {
	$ctx = stream_context_create( array( 'http' => array( 'method' => 'POST', 'header' => "Content-Type: $type\r\n", 'content' => $body, 'timeout' => 20, 'ignore_errors' => true ) ) );
	$res = @file_get_contents( $url . '/?rest_route=/bigloop-call-button/v1/track', false, $ctx );
	$status = 0; $result = '';
	foreach ( $http_response_header ?? array() as $h ) {
		if ( preg_match( '#^HTTP/\S+ (\d+)#', $h, $m ) ) { $status = (int) $m[1]; }
		if ( stripos( $h, 'X-Blcb-Track:' ) === 0 ) { $result = trim( substr( $h, 13 ) ); }
	}
	return array( $status, $result );
}
function fetch( $u ) { return (string) @file_get_contents( $u, false, stream_context_create( array( 'http' => array( 'timeout' => 20, 'ignore_errors' => true ) ) ) ); }

global $wpdb;
$events = $wpdb->prefix . 'blcb_events';
$daily  = $wpdb->prefix . 'blcb_stats_daily';
foreach ( get_posts( array( 'post_type' => array( 'blcb_widget', 'blcb_action', 'blcb_schedule' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) { wp_delete_post( $id, true ); }
wp_set_current_user( 1 );
do_action( 'rest_api_init', rest_get_server() );
r( 'DELETE', '/analytics' );
update_option( 'blcb_settings', array() );
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_blcb_rl_%'" ) as $o ) { delete_option( $o ); }

echo "Setup\n";
list( , $phone ) = r( 'POST', '/actions', array( 'type' => 'phone', 'label' => 'Call', 'value' => array( 'number' => '+15550001111' ) ) );
list( , $wa )    = r( 'POST', '/actions', array( 'type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => array( 'number' => '+15550002222' ) ) );
list( , $other ) = r( 'POST', '/actions', array( 'type' => 'email', 'label' => 'Email', 'value' => array( 'address' => 'a@b.co' ) ) );
list( $s, $w ) = r( 'POST', '/widgets', array( 'title' => 'Stats menu', 'enabled' => true, 'config' => array( 'type' => 'menu', 'actions' => array( array( 'id' => $phone['id'] ), array( 'id' => $wa['id'] ) ) ) ) );
ok( 201 === $s, 'widget created' );
list( , $off ) = r( 'POST', '/widgets', array( 'title' => 'Off', 'enabled' => false, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $other['id'] ) ) ) ) );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Contact us', 'post_status' => 'publish' ) );

echo "Beacon (over HTTP)\n";
list( $st, $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $phone['id'], 'd' => 3, 'o' => $page ) ) );
ok( 204 === $st && 'recorded' === $res, 'valid click recorded (204)', "$st $res" );
list( $st, $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $wa['id'], 'd' => 1, 'o' => 0 ) ), 'text/plain;charset=UTF-8' );
ok( 'recorded' === $res, 'text/plain beacon body accepted', $res );
list( , $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $other['id'], 'd' => 1 ) ) );
ok( 'invalid' === $res, 'action not in widget rejected', $res );
list( , $res ) = beacon( $url, wp_json_encode( array( 'w' => $off['id'], 'a' => $other['id'], 'd' => 1 ) ) );
ok( 'invalid' === $res, 'disabled widget rejected', $res );
list( , $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $phone['id'], 'd' => 9 ) ) );
ok( 'invalid' === $res, 'bad device rejected', $res );
list( $st, $res ) = beacon( $url, 'not json' );
ok( 'invalid' === $res || 400 === $st, 'garbage body rejected', "$st $res" );
list( , $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $phone['id'], 'd' => 2, 'o' => 999999 ) ) );
ok( 'recorded' === $res, 'unknown page id recorded without page', $res );
$row = $wpdb->get_row( "SELECT * FROM {$events} ORDER BY id ASC LIMIT 1", ARRAY_A );
ok( $row && 'phone' === $row['action_type'] && 3 === (int) $row['device'] && (int) $page === (int) $row['object_id'], 'stored row has type from server, device and page' );
ok( ! array_diff( array_keys( $row ), array( 'id', 'widget_id', 'action_id', 'action_type', 'device', 'object_id', 'variant', 'created_at' ) ), 'no personal data columns' );
ok( null === $wpdb->get_var( "SELECT object_id FROM {$events} ORDER BY id DESC LIMIT 1" ), 'unpublished/unknown page stored as NULL' );

// Rate limit: 30 per window.
for ( $i = 0; $i < 30; $i++ ) { $last = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $wa['id'], 'd' => 1 ) ) ); }
ok( 'limited' === $last[1], 'rate limit kicks in', $last[1] );
$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events}" );
ok( 30 === $count, '30 events stored before the limit', (string) $count );

// Disabled tracking.
foreach ( $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_blcb_rl_%'" ) as $o ) { delete_option( $o ); }
r( 'PUT', '/settings', array( 'values' => array( 'analytics' => array( 'enabled' => false ) ) ) );
list( , $res ) = beacon( $url, wp_json_encode( array( 'w' => $w['id'], 'a' => $phone['id'], 'd' => 1 ) ) );
ok( 'disabled' === $res, 'tracking off: nothing stored', $res );
$html = fetch( $url . '/' );
ok( false !== strpos( $html, 'window.blcbConfig={"track":""' ), 'front end: no beacon URL when tracking is off' );
r( 'PUT', '/settings', array( 'values' => array( 'analytics' => array( 'enabled' => true ) ) ) );
list( $s, $body ) = r( 'PUT', '/settings', array( 'values' => array( 'analytics' => array( 'ga_event_name' => 'contact click!' ) ) ) );
ok( 400 === $s && false !== strpos( wp_json_encode( $body ), 'analytics.ga_event_name' ), 'invalid GA event name rejected with field error', (string) $s );
$html = fetch( $url . '/?page_id=' . $page );
ok( (bool) preg_match( '#window\.blcbConfig=\{"track":"[^"]+track","ga":"bigloop_contact_click","obj":' . $page . '\}#', str_replace( '\\/', '/', $html ) ), 'front end: beacon URL, GA event and page id', substr( strstr( $html, 'blcbConfig' ) ?: '', 0, 160 ) );

echo "Report\n";
// Backdate: 4 clicks 3 days ago, 2 clicks 40 days ago (previous period for a 30-day range is 30–59 days ago).
$ins = static function ( $days_ago, $action, $type, $device, $n ) use ( $wpdb, $events, $w ) {
	for ( $i = 0; $i < $n; $i++ ) {
		$wpdb->insert( $events, array( 'widget_id' => $w['id'], 'action_id' => $action, 'action_type' => $type, 'device' => $device, 'created_at' => gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS ) ) );
	}
};
$ins( 3, $wa['id'], 'whatsapp', 3, 4 );
$ins( 40, $phone['id'], 'phone', 1, 2 );
list( $s, $rep ) = r( 'GET', '/analytics' );
ok( 200 === $s && 34 === $rep['total'], 'last 30 days total = 30 today + 4 (34)', (string) ( $rep['total'] ?? $s ) );
ok( 2 === $rep['previous'], 'previous period = 2', (string) $rep['previous'] );
ok( 30 === count( $rep['series'] ) && 30 === end( $rep['series'] )['clicks'], 'daily series has 30 days, today = 30' );
ok( 'whatsapp' === $rep['by_type'][0]['action_type'] && 'Stats menu' === $rep['by_widget'][0]['title'], 'breakdowns by type and widget' );
ok( 3 === array_sum( array( $rep['by_device']['desktop'] > 0, $rep['by_device']['tablet'] > 0, $rep['by_device']['mobile'] > 0 ) ), 'device breakdown has all 3 classes' );
ok( 'Contact us' === ( $rep['pages']['items'][0]['title'] ?? '' ), 'top pages lists the page' );
ok( true === get_option( 'blcb_stats_aggregated_until' ) > '' , 'report caught up the roll-up (watermark set)' );
ok( wp_date( 'Y-m-d' ) === get_option( 'blcb_stats_aggregated_until' ), 'watermark = today (local)' );
ok( 2 <= (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily}" ), 'daily rows written' );
list( $s2, $rep2 ) = r( 'GET', '/analytics' );
ok( $rep2['total'] === $rep['total'] && $rep2['previous'] === $rep['previous'], 'same totals after roll-up (no double counting)' );
list( $s, ) = r( 'GET', '/analytics', null, array( 'from' => '2026-02-30', 'to' => '2026-03-01' ) );
ok( 400 === $s, 'invalid date rejected' );
list( $s, ) = r( 'GET', '/analytics', null, array( 'from' => '2020-01-01', 'to' => '2026-01-01' ) );
ok( 400 === $s, 'range over two years rejected' );

echo "Roll-up and retention\n";
$agg = \Bigloop\CallButton\Core\Plugin::instance()->container()->get( \Bigloop\CallButton\Analytics\Aggregator::class );
$again = $agg->run();
ok( 0 === $again['days'], 'second run processes nothing' );
r( 'PUT', '/settings', array( 'values' => array( 'analytics' => array( 'retention_days' => 30 ) ) ) );
$agg->run();
ok( 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$events} WHERE created_at < %s", gmdate( 'Y-m-d', time() - 31 * DAY_IN_SECONDS ) ) ), 'raw events older than retention deleted' );
list( , $rep3 ) = r( 'GET', '/analytics', null, array( 'from' => wp_date( 'Y-m-d', time() - 59 * DAY_IN_SECONDS ), 'to' => wp_date( 'Y-m-d' ) ) );
ok( 36 === $rep3['total'], 'aggregates survive raw retention (36 over 60 days)', (string) $rep3['total'] );
ok( wp_next_scheduled( 'blcb_daily_maintenance' ) > time(), 'daily job scheduled' );

echo "Overview + list\n";
list( , $ov ) = r( 'GET', '/overview' );
ok( 36 === $ov['clicks']['total'], 'overview total consistent', (string) $ov['clicks']['total'] );

echo "Permissions\n";
wp_set_current_user( 0 );
list( $s, ) = r( 'GET', '/analytics' );
ok( 401 === $s || 403 === $s, 'report requires login' );
list( $s, ) = r( 'DELETE', '/analytics' );
ok( 401 === $s || 403 === $s, 'purge requires login' );
wp_set_current_user( 1 );

echo "Notices\n";
$notices = \Bigloop\CallButton\Core\Plugin::instance()->container()->get( \Bigloop\CallButton\Admin\Notices::class );
delete_user_meta( 1, 'blcb_dismissed_notices' );
$installed = get_option( 'blcb_installed_at' );
update_option( 'blcb_installed_at', time() - 5 * DAY_IN_SECONDS );
ok( null === $notices->eligible( 1, time() ), 'no review notice in the first 14 days' );
update_option( 'blcb_installed_at', time() - 20 * DAY_IN_SECONDS );
$n = $notices->eligible( 1, time() );
ok( $n && 'review' === $n['id'] && 36 === $n['meta']['clicks'], 'review notice after 14 days with 10+ clicks' );
ok( 'review' === ( apply_filters( 'bigloop_cb/admin_boot_notices', array() )[0]['id'] ?? '' ), 'notice reaches the admin boot data' );
list( $s, ) = r( 'POST', '/notices/review', array( 'mode' => 'later' ) );
ok( 200 === $s && null === $notices->eligible( 1, time() ), 'snoozed' );
ok( 'review' === ( $notices->eligible( 1, time() + 31 * DAY_IN_SECONDS )['id'] ?? '' ), 'returns after 30 days' );
r( 'POST', '/notices/review', array( 'mode' => 'never' ) );
ok( 'review' !== ( $notices->eligible( 1, time() + 400 * DAY_IN_SECONDS )['id'] ?? '' ), 'review dismissed for good' );
update_option( 'blcb_installed_at', time() - 40 * DAY_IN_SECONDS );
$n = $notices->eligible( 1, time() );
ok( 'coffee' === ( $n['id'] ?? '' ) && 'https://bigloop.dev/buy-us-coffee/' === ( $n['url'] ?? '' ), 'coffee notice links to bigloop.dev after 30 days' );
add_filter( 'bigloop_cb/donate_url', '__return_empty_string' );
ok( null === $notices->eligible( 1, time() ), 'coffee notice off when the filter returns an empty link' );
remove_filter( 'bigloop_cb/donate_url', '__return_empty_string' );
list( $s, ) = r( 'POST', '/notices/bogus', array( 'mode' => 'never' ) );
ok( 404 === $s, 'unknown notice id rejected' );
delete_user_meta( 1, 'blcb_dismissed_notices' );
update_option( 'blcb_installed_at', $installed );

echo "Purge\n";
list( $s, ) = r( 'DELETE', '/analytics' );
ok( 200 === $s && 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$events}" ) + (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$daily}" ), 'all statistics deleted' );
ok( false === get_option( 'blcb_stats_aggregated_until' ), 'watermark cleared' );

update_option( 'blcb_settings', array() );
wp_delete_post( $page, true );
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
