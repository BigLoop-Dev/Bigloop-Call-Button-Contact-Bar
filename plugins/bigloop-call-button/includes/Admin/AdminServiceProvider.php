<?php
/**
 * Admin services.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Admin;

use Bigloop\CallButton\Database\StatsReader;

use Bigloop\CallButton\Core\Container;
use Bigloop\CallButton\Core\ServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Menu, admin app loader and plugin-list links. Only booted in wp-admin.
 */
final class AdminServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			AdminApp::class,
			static function () {
				return new AdminApp();
			}
		);
		$container->set( Notices::class, static fn( Container $c ) => new Notices( $c->get( StatsReader::class ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$container->get( Notices::class )->hooks();
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_init', array( PrivacyPolicy::class, 'register' ) );
		$app = $container->get( AdminApp::class );
		add_action( 'admin_menu', array( $app, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $app, 'enqueue' ) );
		add_filter( 'admin_body_class', array( $app, 'body_class' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BLCB_FILE ), array( $app, 'action_links' ) );
	}
}
