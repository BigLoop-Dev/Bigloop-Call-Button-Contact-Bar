<?php
/**
 * Core services: settings, permissions, database, post types, public API.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

use Bigloop\CallButton\API\Api;
use Bigloop\CallButton\Database\Migrator;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\PostTypes\PostTypes;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers foundation services.
 */
final class CoreServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			Settings::class,
			static function () {
				return new Settings();
			}
		);
		$container->set(
			Permissions::class,
			static function ( Container $c ) {
				return new Permissions( $c->get( Settings::class ) );
			}
		);
		$container->set(
			Migrator::class,
			static function () {
				global $wpdb;
				return new Migrator( $wpdb );
			}
		);
		$container->set(
			StatsReader::class,
			static function () {
				global $wpdb;
				return new StatsReader( $wpdb );
			}
		);
		$container->set(
			PostTypes::class,
			static function () {
				return new PostTypes();
			}
		);
		$container->set(
			Api::class,
			static function ( Container $c ) {
				return new Api( $c );
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		// Run pending migrations after updates (cheap: compares an autoloaded option).
		$container->get( Migrator::class )->maybe_migrate();

		$container->get( Permissions::class )->hooks();
		add_action( 'init', array( $container->get( PostTypes::class ), 'register' ) );
		add_action( 'wp_initialize_site', array( Lifecycle::class, 'on_new_site' ), 20 );
	}
}
