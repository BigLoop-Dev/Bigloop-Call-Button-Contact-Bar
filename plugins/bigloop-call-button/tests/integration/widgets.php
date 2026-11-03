<?php
/**
 * Integration test for the widget engine (Phase 3). Requires a throwaway WordPress install and,
 * for the front-end checks, that install served over HTTP.
 *
 * Usage: php tests/integration/widgets.php /path/to/wordpress http://127.0.0.1:8890
 *
 * @package Bigloop\CallButton
 */

// phpcs:disable

$wp_dir = $argv[1] ?? '';
$url    = rtrim( $argv[2] ?? '', '/' );
if ( ! is_file( $wp_dir . '/wp-load.php' ) || '' === $url ) {
	fwrite( STDERR, "Usage: php widgets.php /path/to/wordpress http://host:port\n" );
	exit( 2 );
}
$_SERVER['HTTP_HOST']   = parse_url( $url, PHP_URL_HOST );
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
		echo "  ✗ {$label}" . ( '' !== $detail ? " — {$detail}" : '' ) . "\n";
	}
}
function req( $method, $path, $body = null ) {
	$query = array();
	if ( false !== strpos( $path, '?' ) ) {
		list( $path, $qs ) = explode( '?', $path, 2 );
		parse_str( $qs, $query );
	}
	$r = new WP_REST_Request( $method, '/bigloop-call-button/v1' . $path );
	$r->set_query_params( $query );
	if ( null !== $body ) {
		$r->set_header( 'content-type', 'application/json' );
		$r->set_body( wp_json_encode( $body ) );
	}
	$res = rest_do_request( $r );
	return array( $res->get_status(), rest_get_server()->response_to_data( $res, false ) );
}
function fetch( $url ) {
	$ctx = stream_context_create( array( 'http' => array( 'timeout' => 20, 'ignore_errors' => true ) ) );
	return (string) @file_get_contents( $url, false, $ctx );
}

$plugin = 'bigloop-call-button/bigloop-call-button.php';
if ( ! is_plugin_active( $plugin ) ) {
	activate_plugin( $plugin );
}

// Clean slate.
foreach ( get_posts( array( 'post_type' => array( 'blcb_widget', 'blcb_action' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}
delete_option( 'blcb_runtime' );

wp_set_current_user( (int) get_user_by( 'login', 'admin' )->ID );
do_action( 'rest_api_init', rest_get_server() );

echo "Action types\n";
list( $s, $types ) = req( 'GET', '/action-types' );
$ids = wp_list_pluck( $types, 'id' );
$builtin = array( 'phone', 'sms', 'whatsapp', 'email', 'map', 'directions', 'booking', 'url', 'custom', 'telegram', 'messenger', 'viber', 'signal', 'line' );
check( 'lists the 14 built-in types (add-ons may add more)', 200 === $s && array() === array_diff( $builtin, $ids ), implode( ',', $ids ) );
foreach ( array( 'phone', 'sms', 'whatsapp', 'email', 'map', 'directions', 'booking', 'url', 'custom', 'telegram', 'messenger', 'viber', 'signal', 'line' ) as $t ) {
	check( "type {$t} present", in_array( $t, $ids, true ) );
}

echo "Actions: validation\n";
list( $s, $body ) = req( 'POST', '/actions', array( 'type' => 'phone', 'value' => array( 'number' => 'call me' ) ) );
check( 'invalid phone rejected (400)', 400 === $s );
check( 'field error at value.number', isset( $body['data']['errors']['value.number'] ) );
list( $s, $body ) = req( 'POST', '/actions', array( 'type' => 'url', 'value' => array( 'url' => 'javascript:alert(1)' ) ) );
check( 'javascript: URL rejected', 400 === $s && isset( $body['data']['errors']['value.url'] ) );
list( $s, $body ) = req( 'POST', '/actions', array( 'type' => 'nope' ) );
check( 'unknown type rejected', 400 === $s && isset( $body['data']['errors']['type'] ) );
list( $s, $body ) = req( 'POST', '/actions', array( 'type' => 'phone', 'icon' => 'not-an-icon', 'value' => array( 'number' => '555 1234' ) ) );
check( 'unknown icon rejected', 400 === $s && isset( $body['data']['errors']['icon'] ) );
list( $s, $body ) = req( 'POST', '/actions/validate', array( 'type' => 'whatsapp', 'value' => array( 'number' => '+1 (555) 123-4567', 'message' => 'Hi there & welcome' ) ) );
check( 'dry-run validate returns link', 'https://wa.me/15551234567?text=Hi%20there%20%26%20welcome' === $body['href'], $body['href'] );

echo "Actions: create\n";
list( $s, $phone ) = req( 'POST', '/actions', array( 'type' => 'phone', 'label' => 'Call <b>us</b>', 'tooltip' => 'Mon–Fri 9–5', 'value' => array( 'number' => '+1 (555) 123-4567 ext 89' ) ) );
check( 'phone created (201)', 201 === $s, wp_json_encode( $body ) );
check( 'tags stripped from label', 'Call us' === $phone['config']['label'], $phone['config']['label'] );
check( 'phone link with extension', 'tel:+15551234567,89' === $phone['href'], $phone['href'] );
list( $s, $wa ) = req( 'POST', '/actions', array( 'type' => 'whatsapp', 'label' => 'WhatsApp', 'value' => array( 'number' => '+44 20 7946 0958' ) ) );
check( 'whatsapp created', 201 === $s && 'https://wa.me/442079460958' === $wa['href'], $wa['href'] ?? '' );
list( $s, $mail ) = req( 'POST', '/actions', array( 'type' => 'email', 'label' => 'Email "us"', 'value' => array( 'address' => 'hello@example.com', 'subject' => 'Quote request' ) ) );
check( 'email created', 201 === $s && 'mailto:hello@example.com?subject=Quote%20request' === $mail['href'], $mail['href'] ?? '' );
list( $s, $viber ) = req( 'POST', '/actions', array( 'type' => 'viber', 'value' => array( 'number' => '+380 44 123 4567' ) ) );
check( 'viber link', 'viber://chat?number=%2B380441234567' === ( $viber['href'] ?? '' ), $viber['href'] ?? '' );
list( $s, $dir ) = req( 'POST', '/actions', array( 'type' => 'directions', 'enabled' => false, 'value' => array( 'destination' => '10 Downing St, London' ) ) );
check( 'disabled action created', 201 === $s && false === $dir['enabled'] );
check( 'title defaults to type label', 'Directions' === $dir['title'], $dir['title'] );

list( $s, $list ) = req( 'GET', '/actions' );
check( 'list returns 5 actions', 5 === $list['total'] );

echo "Widgets: validation\n";
list( $s, $body ) = req( 'POST', '/widgets', array( 'title' => 'Bad', 'enabled' => true, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $phone['id'] ), array( 'id' => $wa['id'] ) ) ) ) );
check( 'button with two actions rejected', 400 === $s && isset( $body['data']['errors']['actions'] ) );
list( $s, $body ) = req( 'POST', '/widgets', array( 'title' => 'Empty', 'enabled' => true, 'config' => array( 'type' => 'menu', 'actions' => array() ) ) );
check( 'enabled widget without actions rejected', 400 === $s );
list( $s, $body ) = req( 'POST', '/widgets', array( 'title' => '', 'config' => array( 'type' => 'menu', 'appearance' => array( 'size' => 500, 'bg' => 'red' ), 'actions' => array( array( 'id' => 999999 ) ) ) ) );
check( 'leaf errors reported with paths', 400 === $s && isset( $body['data']['errors']['appearance.size'], $body['data']['errors']['appearance.bg'], $body['data']['errors']['actions.0'], $body['data']['errors']['title'] ), wp_json_encode( $body['data']['errors'] ?? array() ) );
list( $s, $defaults ) = req( 'GET', '/widgets/defaults?type=bar' );
check( 'bar defaults hide on desktop', false === $defaults['visibility']['desktop'] && true === $defaults['visibility']['mobile'] );

echo "Widgets: create\n";
list( $s, $menu ) = req(
	'POST',
	'/widgets',
	array(
		'title'   => 'Main "contact" menu',
		'enabled' => true,
		'config'  => array(
			'type'       => 'menu',
			'actions'    => array( array( 'id' => $phone['id'] ), array( 'id' => $wa['id'], 'label' => 'Chat on WhatsApp' ), array( 'id' => $mail['id'] ), array( 'id' => $dir['id'] ), array( 'id' => $viber['id'] ) ),
			'appearance' => array( 'shape' => 'squircle', 'bg' => '#123456', 'pulse' => true ),
			'position'   => array( 'desktop' => array( 'anchor' => 'bottom-left', 'offset_x' => 30, 'offset_y' => 40 ) ),
			'ext'        => array( 'pro' => array( 'ab_test' => 7, 'note' => '<script>x</script>ok' ) ),
		),
	)
);
check( 'menu widget created', 201 === $s, wp_json_encode( $menu ) );
check( 'ext data preserved and cleaned', 7 === $menu['config']['ext']['pro']['ab_test'] && 'xok' === $menu['config']['ext']['pro']['note'], wp_json_encode( $menu['config']['ext'] ?? null ) );
check( 'unspecified leaves get defaults', 'bottom-right' === $menu['config']['position']['mobile']['anchor'] && 56 === $menu['config']['appearance']['size'] );

list( $s, $bar ) = req( 'POST', '/widgets', array( 'title' => 'Mobile bar', 'enabled' => true, 'config' => array( 'type' => 'bar', 'actions' => array( array( 'id' => $phone['id'] ), array( 'id' => $wa['id'] ), array( 'id' => $mail['id'] ) ), 'bar' => array( 'use_action_colors' => true ) ) ) );
check( 'bar widget created', 201 === $s );
list( $s, $btn ) = req( 'POST', '/widgets', array( 'title' => 'Call button', 'enabled' => false, 'config' => array( 'type' => 'button', 'actions' => array( array( 'id' => $phone['id'] ) ), 'content' => array( 'show_label' => true ) ) ) );
check( 'disabled button widget created', 201 === $s && false === $btn['enabled'] );

echo "Runtime snapshot\n";
$compiler = bigloop_call_button()->container()->get( \Bigloop\CallButton\Rendering\RuntimeCompiler::class );
$snap     = get_option( 'blcb_runtime' );
check( 'snapshot stored after changes', is_array( $snap ) && BLCB_VERSION === $snap['version'] );
check( 'snapshot has only enabled widgets (2)', 2 === count( $snap['widgets'] ), (string) count( $snap['widgets'] ) );
$m = array_values( array_filter( $snap['widgets'], fn( $w ) => $w['id'] === $menu['id'] ) )[0] ?? null;
check( 'disabled action excluded from menu (4 of 5)', $m && 4 === count( $m['actions'] ), $m ? (string) count( $m['actions'] ) : 'missing' );
check( 'label override applied', $m && 'Chat on WhatsApp' === $m['actions'][1]['label'] );
check( 'new tab: whatsapp yes, tel no', $m && true === $m['actions'][1]['new_tab'] && false === $m['actions'][0]['new_tab'] );
check( 'icon svg resolved', $m && 0 === strpos( $m['actions'][0]['icon'], '<svg' ) );

echo "Front end (HTTP)\n";
$html = fetch( $url . '/' );
check( 'home page fetched', '' !== $html && false !== stripos( $html, '</html>' ), substr( $html, 0, 200 ) );
check( 'frontend.css enqueued', false !== strpos( $html, 'bigloop-call-button/assets/build/frontend.css' ) );
check( 'frontend.js enqueued deferred', (bool) preg_match( '#<script[^>]+bigloop-call-button/assets/build/frontend\.js[^>]*defer#', $html ) || (bool) preg_match( '#<script[^>]+defer[^>]+bigloop-call-button/assets/build/frontend\.js#', $html ) );
check( 'inline css printed', false !== strpos( $html, '<style id="blcb-inline-css">' ) );
check( 'menu markup with details', false !== strpos( $html, 'id="blcb-w-' . $menu['id'] . '"' ) && false !== strpos( $html, '<details class="blcb-menu">' ) );
check( 'bar markup', false !== strpos( $html, '<nav id="blcb-w-' . $bar['id'] . '"' ) && false !== strpos( $html, 'blcb-bar--action-colors' ) );
check( 'disabled widget not rendered', false === strpos( $html, 'id="blcb-w-' . $btn['id'] . '"' ) );
check( 'tel link with extension', false !== strpos( $html, 'href="tel:+15551234567,89"' ) );
check( 'viber link survives esc_url', false !== strpos( $html, 'href="viber://chat?number=%2B380441234567"' ) );
check( 'whatsapp opens in new tab safely', (bool) preg_match( '#href="https://wa\.me/442079460958"[^>]*target="_blank" rel="noopener noreferrer"#', $html ) );
check( 'quotes in names escaped', false === strpos( $html, 'Email "us"' ) || false !== strpos( $html, 'Email &quot;us&quot;' ) );
check( 'squircle + pulse + anchor-left classes', (bool) preg_match( '#class="blcb blcb-w blcb-w--menu blcb-shape--squircle[^"]*blcb-anchor-left[^"]*blcb-pulse#', $html ) );
check( 'desktop position css', false !== strpos( $html, '#blcb-w-' . $menu['id'] . '{top:auto;bottom:auto;left:auto;right:auto;transform:none;bottom:calc(40px' ) );
check( 'bar hidden on desktop + body space reserved', false !== strpos( $html, '@media (min-width:1025px){#blcb-w-' . $bar['id'] . '{display:none!important}}' ) && false !== strpos( $html, ':not([hidden])){padding-bottom:calc(60px' ) );
check( 'no front-end assets in REST responses', false === strpos( fetch( $url . '/?rest_route=/' ), 'blcb-inline-css' ) );

echo "Disable / delete propagation\n";
list( $s ) = req( 'POST', '/widgets/' . $bar['id'] . '/status', array( 'enabled' => false ) );
check( 'bar disabled via status endpoint', 200 === $s );
check( 'disabled bar gone from page', false === strpos( fetch( $url . '/' ), 'id="blcb-w-' . $bar['id'] . '"' ) );
list( $s, $del ) = req( 'DELETE', '/actions/' . $wa['id'] );
check( 'deleting action reports affected widgets (2)', 200 === $s && 2 === $del['widgets_affected'], wp_json_encode( $del ) );
list( $s, $menu2 ) = req( 'GET', '/widgets/' . $menu['id'] );
check( 'reference removed from widget', 4 === $menu2['actions_count'], (string) $menu2['actions_count'] );
check( 'deleted action gone from page', false === strpos( fetch( $url . '/' ), 'wa.me/442079460958' ) );
list( $s, $usage ) = req( 'GET', '/actions/' . $phone['id'] );
check( 'usage lists widgets using phone action', 3 === count( $usage['used_by'] ), wp_json_encode( $usage['used_by'] ) );

echo "Duplicate / reorder / list\n";
list( $s, $copy ) = req( 'POST', '/widgets/' . $menu['id'] . '/duplicate' );
check( 'duplicate created disabled', 201 === $s && false === $copy['enabled'] && false !== strpos( $copy['title'], '(copy)' ) );
check( 'duplicate keeps config', $copy['config']['appearance']['shape'] === 'squircle' );
list( $s ) = req( 'POST', '/widgets/reorder', array( 'ids' => array( $copy['id'], $bar['id'], $menu['id'], $btn['id'] ) ) );
list( $s, $list ) = req( 'GET', '/widgets?orderby=order' );
check( 'reorder applied', array( $copy['id'], $bar['id'], $menu['id'], $btn['id'] ) === wp_list_pluck( $list['items'], 'id' ) );
list( $s, $list ) = req( 'GET', '/widgets?status=enabled' );
check( 'status filter', 1 === $list['total'] );
list( $s, $list ) = req( 'GET', '/widgets?type=bar' );
check( 'type filter', 1 === $list['total'] && 'bar' === $list['items'][0]['type'] );
list( $s, $list ) = req( 'GET', '/widgets?search=contact' );
check( 'search', 2 === $list['total'], (string) $list['total'] );
list( $s, $list ) = req( 'GET', '/widgets?per_page=2&page=2' );
check( 'pagination', 4 === $list['total'] && 2 === count( $list['items'] ) );
check( 'preview url provided', false !== strpos( $list['items'][0]['preview_url'], 'blcb_preview=' ) );

echo "Shortcode & block\n";
$sc = do_shortcode( '[bigloop_contact id="' . $menu['id'] . '" align="center"]' );
check( 'shortcode renders inline buttons', false !== strpos( $sc, 'blcb-inline blcb-shape--squircle blcb-inline--center' ) && false !== strpos( $sc, 'tel:+15551234567,89' ) );
check( 'shortcode for disabled widget renders nothing', '' === do_shortcode( '[bigloop_contact id="' . $btn['id'] . '"]' ) );
check( 'shortcode floating mode returns empty content', '' === do_shortcode( '[bigloop_contact id="' . $menu['id'] . '" display="floating"]' ) );
check( 'block registered', WP_Block_Type_Registry::get_instance()->is_registered( 'bigloop/contact-widget' ) );
$block_html = do_blocks( '<!-- wp:bigloop/contact-widget {"widgetId":' . $menu['id'] . '} /-->' );
check( 'block renders inline buttons in wrapper', false !== strpos( $block_html, 'wp-block-bigloop-contact-widget' ) && false !== strpos( $block_html, 'blcb-inline' ) );
$post_id = wp_insert_post( array( 'post_title' => 'Shortcode page', 'post_status' => 'publish', 'post_type' => 'page', 'post_content' => '[bigloop_contact id="' . $menu['id'] . '"]' ) );
$page    = fetch( $url . '/?page_id=' . $post_id );
check( 'shortcode on real page enqueues css', false !== strpos( $page, 'blcb-inline' ) && false !== strpos( $page, 'frontend.css' ) );
wp_delete_post( $post_id, true );

echo "Preview mode\n";
$_GET['blcb_preview'] = (string) $btn['id'];
$fc = new \Bigloop\CallButton\Frontend\FrontendController( $compiler, new \Bigloop\CallButton\Rendering\Renderer() );
$fc->prepare();
check( 'admin preview queues disabled widget only', array( $btn['id'] ) === array_keys( $fc->queued() ) );
wp_set_current_user( 0 );
$fc = new \Bigloop\CallButton\Frontend\FrontendController( $compiler, new \Bigloop\CallButton\Rendering\Renderer() );
$fc->prepare();
check( 'anonymous preview param ignored', ! isset( $fc->queued()[ $btn['id'] ] ) && isset( $fc->queued()[ $menu['id'] ] ) );
unset( $_GET['blcb_preview'] );
check( 'anonymous cannot list widgets', 401 === req( 'GET', '/widgets' )[0] );

echo "\n{$passes} passed, {$failures} failed\n";
exit( $failures ? 1 : 0 );
