<?php
/**
 * Integration test for the Elementor widget. Needs Elementor active and the install served over HTTP.
 * Usage: php tests/integration/elementor.php /path/to/wordpress http://127.0.0.1:8890
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

if ( ! did_action( 'elementor/loaded' ) ) { echo "Elementor is not active — skipped.\n"; exit( 0 ); }
wp_set_current_user( 1 );
do_action( 'rest_api_init', rest_get_server() );

list( , $phone ) = r( 'POST', '/actions', array( 'type' => 'phone', 'label' => 'Call the studio', 'value' => array( 'number' => '+15550001111' ) ) );
list( , $w )     = r( 'POST', '/widgets', array( 'title' => 'Elementor embed', 'enabled' => true, 'config' => array( 'type' => 'menu', 'actions' => array( array( 'id' => $phone['id'] ) ), 'visibility' => array( 'desktop' => false, 'tablet' => false, 'mobile' => false ) ) ) );

echo "Registration\n";
$type = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( 'bigloop_contact' );
ok( $type instanceof \Bigloop\CallButton\Integrations\Elementor\ContactWidget, 'widget type registered' );
ok( 'Contact Widget' === $type->get_title() && in_array( 'general', $type->get_categories(), true ), 'title and category' );
$controls = $type->get_controls();
ok( isset( $controls['widget_id'], $controls['display'], $controls['align'] ), 'controls registered' );
// Elementor keeps full control definitions (like select options) only in the editor, so check the option source.
$opts = ( new ReflectionMethod( $type, 'widget_options' ) );
$opts->setAccessible( true );
$list = $opts->invoke( $type );
ok( isset( $list[ (string) $w['id'] ] ) && 'Elementor embed' === $list[ (string) $w['id'] ], 'widget select lists our widget' );

echo "Render\n";
$render = static function ( $settings ) {
	$el = \Elementor\Plugin::$instance->elements_manager->create_element_instance( array( 'elType' => 'widget', 'widgetType' => 'bigloop_contact', 'id' => 'a1b2c3', 'settings' => $settings ) );
	ob_start();
	$el->print_element();
	return ob_get_clean();
};
$html = $render( array( 'widget_id' => (string) $w['id'], 'display' => 'inline', 'align' => 'center' ) );
ok( false !== strpos( $html, 'blcb-inline' ) && false !== strpos( $html, 'tel:+15550001111' ), 'inline buttons rendered', substr( strip_tags( $html ), 0, 120 ) );
ok( false !== strpos( $html, 'Call the studio' ), 'action label rendered' );
$empty = $render( array( 'widget_id' => '' ) );
ok( false === strpos( $empty, 'blcb-inline' ), 'no output without a widget' );

echo "Front end page\n";
$page = wp_insert_post( array( 'post_type' => 'page', 'post_title' => 'Elementor page', 'post_status' => 'publish' ) );
$data = array( array( 'id' => 'sec1', 'elType' => 'container', 'settings' => array(), 'elements' => array( array( 'id' => 'wid1', 'elType' => 'widget', 'widgetType' => 'bigloop_contact', 'settings' => array( 'widget_id' => (string) $w['id'], 'display' => 'inline' ), 'elements' => array() ) ) ) );
update_post_meta( $page, '_elementor_edit_mode', 'builder' );
update_post_meta( $page, '_elementor_template_type', 'wp-page' );
update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
$out = (string) @file_get_contents( $url . '/?page_id=' . $page );
ok( false !== strpos( $out, 'elementor-widget-bigloop_contact' ), 'Elementor printed the widget wrapper' );
ok( false !== strpos( $out, 'tel:+15550001111' ) && false !== strpos( $out, 'frontend.css' ), 'buttons and plugin styles on the page' );

wp_delete_post( $page, true );
r( 'DELETE', '/widgets/' . $w['id'] );
r( 'DELETE', '/actions/' . $phone['id'] );
echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
