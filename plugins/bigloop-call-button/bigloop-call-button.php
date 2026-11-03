<?php
/**
 * Plugin Name:       Bigloop Call Button & Contact Bar
 * Plugin URI:        https://bigloop.dev/
 * Description:       Floating contact buttons, expandable action menus and sticky contact bars with scheduling, display rules and privacy-friendly click analytics. No account required.
 * Version:           0.6.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Bigloop
 * Author URI:        https://bigloop.dev/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bigloop-call-button
 * Domain Path:       /languages
 *
 * @package Bigloop\CallButton
 */

defined( 'ABSPATH' ) || exit;

define( 'BLCB_VERSION', '0.6.1' );
define( 'BLCB_FILE', __FILE__ );
define( 'BLCB_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLCB_URL', plugin_dir_url( __FILE__ ) );
define( 'BLCB_MIN_PHP', '7.4' );
define( 'BLCB_MIN_WP', '6.5' );

require_once BLCB_DIR . 'includes/Core/Requirements.php';

if ( ! \Bigloop\CallButton\Core\Requirements::met() ) {
	\Bigloop\CallButton\Core\Requirements::register_notice();
	return;
}

require_once BLCB_DIR . 'includes/Core/Autoloader.php';
\Bigloop\CallButton\Core\Autoloader::register( 'Bigloop\\CallButton\\', BLCB_DIR . 'includes/' );

register_activation_hook( __FILE__, array( \Bigloop\CallButton\Core\Lifecycle::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Bigloop\CallButton\Core\Lifecycle::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Bigloop\CallButton\Core\Plugin::class, 'boot' ), 5 );

/**
 * Returns the plugin instance (service container access for integrations).
 *
 * @return \Bigloop\CallButton\Core\Plugin
 */
function bigloop_call_button() {
	return \Bigloop\CallButton\Core\Plugin::instance();
}
