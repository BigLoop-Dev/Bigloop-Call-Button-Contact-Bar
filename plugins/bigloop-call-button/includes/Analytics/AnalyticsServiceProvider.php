<?php
/**
 * Wires click tracking, the roll-up job and the analytics API.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Analytics;

use Bigloop\CallButton\Core\Container;
use Bigloop\CallButton\Core\Permissions;
use Bigloop\CallButton\Core\ServiceProvider;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Frontend\FrontendController;
use Bigloop\CallButton\Rendering\RuntimeCompiler;
use Bigloop\CallButton\Repositories\ActionRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;
use Bigloop\CallButton\REST\AnalyticsController;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics provider.
 */
final class AnalyticsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			Tracker::class,
			static function ( Container $c ) {
				global $wpdb;
				return new Tracker( $wpdb, $c->get( Settings::class ), $c->get( RuntimeCompiler::class ) );
			}
		);
		$container->set(
			Aggregator::class,
			static function ( Container $c ) {
				global $wpdb;
				return new Aggregator( $wpdb, $c->get( Settings::class ), $c->get( StatsReader::class ) );
			}
		);
		$container->set(
			AnalyticsController::class,
			static fn( Container $c ) => new AnalyticsController( $c->get( Tracker::class ), $c->get( StatsReader::class ), $c->get( Aggregator::class ), $c->get( WidgetRepository::class ), $c->get( ActionRepository::class ) )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$container->get( Aggregator::class )->hooks();

		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$container->get( AnalyticsController::class )->register_routes();
			}
		);

		// Front-end config for the click beacon and Google Analytics events. Runs before footer scripts print, after
		// shortcodes and blocks have had the chance to enqueue the runtime.
		add_action(
			'wp_footer',
			static function () use ( $container ) {
				if ( ! wp_script_is( FrontendController::SCRIPT_HANDLE, 'enqueued' ) ) {
					return;
				}
				$config = self::frontend_config( $container->get( Settings::class ) );
				wp_add_inline_script( FrontendController::SCRIPT_HANDLE, 'window.blcbConfig=' . wp_json_encode( $config ) . ';', 'before' );
			},
			5
		);
	}

	/**
	 * Config for the front-end runtime.
	 *
	 * @param Settings $settings Settings.
	 * @return array{track:string, ga:string, obj:int}
	 */
	public static function frontend_config( Settings $settings ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview switch.
		$preview = isset( $_GET['blcb_preview'] );
		$track   = (bool) $settings->get( 'analytics.enabled', true ) && ! $preview;
		if ( $track && $settings->get( 'analytics.exclude_admins', true ) && is_user_logged_in() && Permissions::current_user_can_manage() ) {
			$track = false;
		}
		$ga_name = (string) $settings->get( 'analytics.ga_event_name', '' );

		$config = array(
			'track' => $track ? rest_url( 'bigloop-call-button/v1/track' ) : '',
			'ga'    => $settings->get( 'analytics.ga_events', true ) && ! $preview ? ( '' !== $ga_name ? $ga_name : 'bigloop_contact_click' ) : '',
			'obj'   => is_singular() ? (int) get_queried_object_id() : 0,
		);

		/**
		 * Filters the front-end runtime config (click beacon URL, Google Analytics event name, page ID).
		 *
		 * @since 0.5.0
		 *
		 * @param array $config Config.
		 */
		return (array) apply_filters( 'bigloop_cb/frontend_config', $config );
	}
}
