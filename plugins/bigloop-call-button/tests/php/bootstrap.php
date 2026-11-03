<?php
/**
 * Unit-test bootstrap: no WordPress, no database. Provides in-memory stubs for the few WP functions
 * that pure classes touch. Works with PHPUnit (composer test) or the bundled fallback runner (run.php).
 *
 * @package Bigloop\CallButton
 */

// phpcs:disable

define( 'ABSPATH', __DIR__ . '/' );
define( 'BLCB_VERSION', '0.0.0-test' );
define( 'DAY_IN_SECONDS', 86400 );

require dirname( __DIR__, 2 ) . '/includes/Core/Autoloader.php';
\Bigloop\CallButton\Core\Autoloader::register( 'Bigloop\\CallButton\\', dirname( __DIR__, 2 ) . '/includes/' );

/** In-memory option store for unit tests. */
$GLOBALS['blcb_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['blcb_test_options'] ) ? $GLOBALS['blcb_test_options'][ $name ] : $default;
	}
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['blcb_test_options'][ $name ] = $value;
		return true;
	}
	function add_option( $name, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( $name, $GLOBALS['blcb_test_options'] ) ) {
			return false;
		}
		$GLOBALS['blcb_test_options'][ $name ] = $value;
		return true;
	}
	function delete_option( $name ) {
		unset( $GLOBALS['blcb_test_options'][ $name ] );
		return true;
	}
	function apply_filters( $hook, $value ) {
		return $value;
	}
	function do_action( $hook, ...$args ) {}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}

if ( ! class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
	require __DIR__ . '/TestCaseShim.php';
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );
		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}
		return trim( $text );
	}
}
