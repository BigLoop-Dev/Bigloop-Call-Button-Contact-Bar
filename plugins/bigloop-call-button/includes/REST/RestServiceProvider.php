<?php
/**
 * Registers REST controllers.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Core\Container;
use Bigloop\CallButton\Core\ServiceProvider;
use Bigloop\CallButton\Database\Migrator;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * REST namespace: bigloop-call-button/v1.
 */
final class RestServiceProvider implements ServiceProvider {

	public const REST_NAMESPACE = 'bigloop-call-button/v1';

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			SettingsController::class,
			static function ( Container $c ) {
				return new SettingsController( $c->get( Settings::class ) );
			}
		);
		$container->set(
			OverviewController::class,
			static function ( Container $c ) {
				return new OverviewController( $c->get( StatsReader::class ), $c->get( Migrator::class ) );
			}
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$container->get( SettingsController::class )->register_routes();
				$container->get( OverviewController::class )->register_routes();
			}
		);
	}
}
