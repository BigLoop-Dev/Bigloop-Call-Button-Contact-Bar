<?php
/**
 * Integration smoke test against a real WordPress install (CLI).
 *
 * Usage: php tests/integration/smoke.php /path/to/wordpress
 *
 * Covers: activation + migrations, idempotent re-migration, CPT registration, dynamic capability,
 * REST permission + settings validation, overview payload, deactivation, uninstall (opt-in only).
 *
 * WARNING: destructive for the target site's plugin data. Use a throwaway install.
 *
 * @package Bigloop\CallButton
 */

// phpcs:disable

$wp_dir = $argv[1] ?? '';
if ( ! is_file( $wp_dir . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php smoke.php /path/to/wordpress\n" );
	exit( 2 );
}

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $wp_dir . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$failures = 0;
$passes   = 0;
function check( $label, $cond, $detail = '' ) {
	global $failures, $passes;
	if ( $cond ) {
		$passes++;
		echo "  ✓ {$label}\n";
	} else {
		$failures++;
		echo "  ✗ {$label}" . ( $detail ? " — {$detail}" : '' ) . "\n";
	}
}

$plugin = 'bigloop-call-button/bigloop-call-button.php';
global $wpdb;

echo "Activation\n";
deactivate_plugins( $plugin, true );
// Start from a clean slate so the test is repeatable.
foreach ( get_posts( array( 'post_type' => array( 'blcb_widget', 'blcb_action', 'blcb_schedule', 'blcb_preset' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
delete_option( 'blcb_settings' );
delete_option( 'blcb_stats_aggregated_until' );
delete_option( 'blcb_db_version' );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blcb_events" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blcb_stats_daily" );
$result = activate_plugin( $plugin );
check( 'activate_plugin() succeeds', ! is_wp_error( $result ), is_wp_error( $result ) ? $result->get_error_message() : '' );
check( 'plugin is active', is_plugin_active( $plugin ) );

// The plugin file was included during activation; boot it as plugins_loaded would.
if ( ! did_action( 'bigloop_cb/loaded' ) ) {
	\Bigloop\CallButton\Core\Plugin::boot();
	do_action( 'init' );
}

check( 'schema version recorded = 1', 1 === (int) get_option( 'blcb_db_version' ), var_export( get_option( 'blcb_db_version' ), true ) );
check( 'install timestamp recorded', (int) get_option( 'blcb_installed_at' ) > 0 );
$tables = array( $wpdb->prefix . 'blcb_events', $wpdb->prefix . 'blcb_stats_daily' );
foreach ( $tables as $t ) {
	check( "table {$t} exists", $t === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) );
}
check( 'no migration error stored', false === get_option( 'blcb_db_error' ) );
check( 'migration lock released', false === get_option( 'blcb_db_migrating' ) );

$migrator = bigloop_call_button()->container()->get( \Bigloop\CallButton\Database\Migrator::class );
check( 're-running migrate() is safe', true === $migrator->migrate() );
check( 'needs_migration() false when current', false === $migrator->needs_migration() );

echo "Container & API\n";
check( 'bigloop_cb/loaded fired', did_action( 'bigloop_cb/loaded' ) > 0 );
$api = bigloop_call_button()->container()->get( \Bigloop\CallButton\API\Api::class );
check( 'API version 1.1.0', '1.1.0' === $api->version() );
check( 'API reads defaults', false === $api->setting( 'general.delete_data_on_uninstall' ) );

echo "Post types\n";
foreach ( array( 'blcb_widget', 'blcb_action', 'blcb_schedule', 'blcb_preset' ) as $pt ) {
	$obj = get_post_type_object( $pt );
	check( "{$pt} registered and private", $obj && ! $obj->public && ! $obj->show_in_rest );
}

echo "Capabilities\n";
$admin_id  = (int) get_user_by( 'login', 'admin' )->ID;
$editor_id = username_exists( 'blcb_editor' ) ?: wp_insert_user( array( 'user_login' => 'blcb_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => 'editor@example.com' ) );
$author_id = username_exists( 'blcb_author' ) ?: wp_insert_user( array( 'user_login' => 'blcb_author', 'user_pass' => wp_generate_password(), 'role' => 'author', 'user_email' => 'author@example.com' ) );
check( 'admin can manage', user_can( $admin_id, 'manage_bigloop_contact' ) );
check( 'editor cannot manage by default', ! user_can( $editor_id, 'manage_bigloop_contact' ) );

echo "REST\n";
do_action( 'rest_api_init', rest_get_server() );
$server = rest_get_server();
$routes = $server->get_routes();
check( 'GET/PUT /settings route registered', isset( $routes['/bigloop-call-button/v1/settings'] ) );
check( 'GET /overview route registered', isset( $routes['/bigloop-call-button/v1/overview'] ) );

wp_set_current_user( $author_id );
$res = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) );
check( 'author gets 403 on /overview', 403 === $res->get_status(), (string) $res->get_status() );

wp_set_current_user( 0 );
$res = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/settings' ) );
check( 'anonymous gets 401 on /settings', 401 === $res->get_status(), (string) $res->get_status() );

wp_set_current_user( $admin_id );
$res  = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) );
$body = $res->get_data();
check( 'admin gets 200 on /overview', 200 === $res->get_status() );
check( 'overview has zero widgets', 0 === $body['widgets']['total'] );
check( 'overview reports db schema current', $body['system']['db_version'] === $body['system']['db_latest'] );
check( 'overview pro inactive', false === $body['pro']['active'] );

// Seed stats: 2 aggregated clicks yesterday + 3 raw today + 1 widget.
$widget = wp_insert_post( array( 'post_type' => 'blcb_widget', 'post_title' => 'Main call button', 'post_status' => 'publish' ) );
wp_insert_post( array( 'post_type' => 'blcb_widget', 'post_title' => 'Off widget', 'post_status' => 'draft' ) );
$wpdb->insert( $wpdb->prefix . 'blcb_stats_daily', array( 'day' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ), 'widget_id' => $widget, 'action_id' => 1, 'action_type' => 'whatsapp', 'device' => 3, 'clicks' => 2 ) );
for ( $i = 0; $i < 3; $i++ ) {
	$wpdb->insert( $wpdb->prefix . 'blcb_events', array( 'widget_id' => $widget, 'action_id' => 2, 'action_type' => 'phone', 'device' => 3, 'created_at' => gmdate( 'Y-m-d H:i:s' ) ) );
}
$body = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) )->get_data();
check( 'total clicks = aggregated + today raw (5)', 5 === $body['clicks']['total'], (string) $body['clicks']['total'] );
check( 'top action = phone (3)', 'phone' === ( $body['clicks']['top_action']['action_type'] ?? null ) && 3 === $body['clicks']['top_action']['clicks'] );
check( 'enabled/disabled widget counts', 1 === $body['widgets']['enabled'] && 1 === $body['widgets']['disabled'] );
check( 'recent events include widget title', 'Main call button' === ( $body['recent'][0]['widget_title'] ?? '' ) );

// Unaggregated raw click from yesterday must still be counted until the roll-up advances the watermark.
$wpdb->insert( $wpdb->prefix . 'blcb_events', array( 'widget_id' => $widget, 'action_id' => 3, 'action_type' => 'email', 'device' => 1, 'created_at' => gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) ) );
$body = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) )->get_data();
check( 'unaggregated raw click from yesterday counted (6)', 6 === $body['clicks']['total'], (string) $body['clicks']['total'] );
// Simulate roll-up having processed everything before today: yesterday's raw row is now represented by daily rows only.
update_option( 'blcb_stats_aggregated_until', gmdate( 'Y-m-d' ) );
$body = rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) )->get_data();
check( 'after watermark: daily (2) + raw since watermark (3) = 5', 5 === $body['clicks']['total'], (string) $body['clicks']['total'] );
check( 'after watermark: 30-day breakdown has no double count', 5 === $body['clicks']['last_30'], (string) $body['clicks']['last_30'] );
delete_option( 'blcb_stats_aggregated_until' );

$req = new WP_REST_Request( 'PUT', '/bigloop-call-button/v1/settings' );
$req->set_header( 'content-type', 'application/json' );
$req->set_body( wp_json_encode( array( 'values' => array( 'general' => array( 'delete_data_on_uninstall' => 'yes' ) ) ) ) );
$res = rest_do_request( $req );
check( 'invalid setting rejected with 400', 400 === $res->get_status() );
check( 'field error reported', isset( $res->get_data()['data']['errors']['general.delete_data_on_uninstall'] ) );

$req->set_body( wp_json_encode( array( 'values' => array( 'nope' => array( 'x' => 1 ) ) ) ) );
check( 'unknown section rejected', 400 === rest_do_request( $req )->get_status() );

$req->set_body( wp_json_encode( array( 'values' => array( 'access' => array( 'allow_editors' => true ) ) ) ) );
$res = rest_do_request( $req );
check( 'valid partial update saved', 200 === $res->get_status() && true === $res->get_data()['values']['access']['allow_editors'] );
check( 'other settings untouched', false === $res->get_data()['values']['general']['delete_data_on_uninstall'] );

// Fresh Settings instance reads the stored option.
$settings = bigloop_call_button()->container()->get( \Bigloop\CallButton\Settings\Settings::class );
clean_user_cache( $editor_id );
$editor = new WP_User( $editor_id );
check( 'editor can manage once allowed', $editor->has_cap( 'manage_bigloop_contact' ) );
wp_set_current_user( $editor_id );
check( 'editor gets 200 on /overview once allowed', 200 === rest_do_request( new WP_REST_Request( 'GET', '/bigloop-call-button/v1/overview' ) )->get_status() );
wp_set_current_user( $admin_id );

echo "Deactivation keeps data\n";
deactivate_plugins( $plugin );
check( 'tables still exist after deactivation', $tables[0] === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables[0] ) ) );
check( 'settings still exist after deactivation', false !== get_option( 'blcb_settings' ) );

echo "Uninstall\n";
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	define( 'WP_UNINSTALL_PLUGIN', $plugin );
}
$uninstall = WP_PLUGIN_DIR . '/bigloop-call-button/uninstall.php';
include $uninstall; // delete_data_on_uninstall is false: must keep everything.
check( 'uninstall without opt-in keeps tables', $tables[0] === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables[0] ) ) );
check( 'uninstall without opt-in keeps widgets', null !== get_post( $widget ) );

$s = get_option( 'blcb_settings' );
$s['general']['delete_data_on_uninstall'] = true;
update_option( 'blcb_settings', $s );
blcb_uninstall_site();
check( 'uninstall with opt-in drops tables', null === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables[0] ) ) );
check( 'uninstall with opt-in deletes widgets', null === get_post( $widget ) );
check( 'uninstall with opt-in deletes options', false === get_option( 'blcb_settings' ) && false === get_option( 'blcb_db_version' ) );

echo "\nRe-activation (fresh install)\n";
$result = activate_plugin( $plugin );
check( 're-activation succeeds', ! is_wp_error( $result ) );
check( 'tables recreated', $tables[0] === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tables[0] ) ) );

wp_delete_user( $editor_id );
wp_delete_user( $author_id );

echo "\n{$passes} passed, {$failures} failed\n";
exit( $failures ? 1 : 0 );
