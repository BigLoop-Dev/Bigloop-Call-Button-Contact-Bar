<?php
/**
 * Contact widget engine: action types, storage, compiler, rendering, integrations and REST.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Widgets;

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Conditions\ConditionRegistry;
use Bigloop\CallButton\Domain\ScheduleSanitizer;
use Bigloop\CallButton\Geo\GeoService;
use Bigloop\CallButton\Repositories\ScheduleRepository;
use Bigloop\CallButton\REST\LookupController;
use Bigloop\CallButton\REST\SchedulesController;
use Bigloop\CallButton\Scheduler\ScheduleService;
use Bigloop\CallButton\Settings\Settings;
use Bigloop\CallButton\Core\Container;
use Bigloop\CallButton\Core\ServiceProvider;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Domain\ActionSanitizer;
use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\Frontend\FrontendController;
use Bigloop\CallButton\Integrations\Elementor\Elementor;
use Bigloop\CallButton\Icons\IconRegistry;
use Bigloop\CallButton\Integrations\Block;
use Bigloop\CallButton\Integrations\Shortcode;
use Bigloop\CallButton\Rendering\Renderer;
use Bigloop\CallButton\Rendering\RuntimeCompiler;
use Bigloop\CallButton\Repositories\ActionRepository;
use Bigloop\CallButton\Repositories\PresetRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;
use Bigloop\CallButton\REST\PresetsController;
use Bigloop\CallButton\REST\ActionsController;
use Bigloop\CallButton\REST\WidgetsController;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the widget engine.
 */
final class WidgetsServiceProvider implements ServiceProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			IconRegistry::class,
			static function () {
				return new IconRegistry( BLCB_DIR . 'assets/icons/icons.json' );
			}
		);
		$container->set(
			ActionTypeRegistry::class,
			static function () {
				$registry = ActionTypeRegistry::with_defaults();
				/**
				 * Register additional action types.
				 *
				 * @since 0.2.0
				 *
				 * @param ActionTypeRegistry $registry Registry; call $registry->register( new ActionType( … ) ).
				 */
				do_action( 'bigloop_cb/register_action_types', $registry );
				return $registry;
			}
		);
		$container->set( ActionRepository::class, static fn() => new ActionRepository() );
		$container->set( WidgetRepository::class, static fn() => new WidgetRepository() );
		$container->set(
			ActionSanitizer::class,
			static function ( Container $c ) {
				$icons = $c->get( IconRegistry::class );
				return new ActionSanitizer(
					$c->get( ActionTypeRegistry::class ),
					array( $icons, 'has' ),
					$c->get( RuntimeCompiler::class )->protocols()
				);
			}
		);
		$container->set(
			WidgetSanitizer::class,
			static function ( Container $c ) {
				$actions = $c->get( ActionRepository::class );
				return new WidgetSanitizer(
					array( $actions, 'exists' ),
					array( $c->get( IconRegistry::class ), 'has' ),
					array( $c->get( ConditionRegistry::class ), 'sanitize' ),
					array( $c->get( ScheduleRepository::class ), 'exists' )
				);
			}
		);
		$container->set( GeoService::class, static fn( Container $c ) => new GeoService( $c->get( Settings::class ) ) );
		$container->set(
			ConditionRegistry::class,
			static function ( Container $c ) {
				$registry = ConditionRegistry::with_defaults(
					array(
						'woocommerce' => class_exists( 'WooCommerce' ),
						'geo'         => $c->get( GeoService::class )->enabled(),
					)
				);
				/**
				 * Register additional display-rule conditions.
				 *
				 * @since 0.4.0
				 *
				 * @param ConditionRegistry $registry Registry; call $registry->register( new Condition( … ) ).
				 */
				do_action( 'bigloop_cb/register_conditions', $registry );
				return $registry;
			}
		);
		$container->set( ScheduleRepository::class, static fn() => new ScheduleRepository() );
		$container->set( ScheduleService::class, static fn( Container $c ) => new ScheduleService( $c->get( ScheduleRepository::class ) ) );
		$container->set( SchedulesController::class, static fn( Container $c ) => new SchedulesController( $c->get( ScheduleRepository::class ), $c->get( WidgetRepository::class ), new ScheduleSanitizer() ) );
		$container->set( LookupController::class, static fn( Container $c ) => new LookupController( $c->get( ConditionRegistry::class ), $c->get( GeoService::class ) ) );
		$container->set(
			RuntimeCompiler::class,
			static fn( Container $c ) => new RuntimeCompiler(
				$c->get( WidgetRepository::class ),
				$c->get( ActionRepository::class ),
				$c->get( ActionTypeRegistry::class ),
				$c->get( IconRegistry::class )
			)
		);
		$container->set( Renderer::class, static fn() => new Renderer() );
		$container->set( FrontendController::class, static fn( Container $c ) => new FrontendController( $c->get( RuntimeCompiler::class ), $c->get( Renderer::class ), $c->get( ConditionRegistry::class ), $c->get( ScheduleService::class ) ) );
		$container->set( Shortcode::class, static fn( Container $c ) => new Shortcode( $c->get( RuntimeCompiler::class ), $c->get( Renderer::class ), $c->get( FrontendController::class ) ) );
		$container->set( Block::class, static fn( Container $c ) => new Block( $c->get( Shortcode::class ) ) );
		$container->set(
			WidgetsController::class,
			static fn( Container $c ) => new WidgetsController( $c->get( WidgetRepository::class ), $c->get( WidgetSanitizer::class ), $c->get( StatsReader::class ), $c->get( RuntimeCompiler::class ), $c->get( Renderer::class ) )
		);
		$container->set( PresetRepository::class, static fn() => new PresetRepository() );
		$container->set( PresetsController::class, static fn( Container $c ) => new PresetsController( $c->get( PresetRepository::class ), $c->get( WidgetSanitizer::class ) ) );
		$container->set(
			ActionsController::class,
			static fn( Container $c ) => new ActionsController(
				$c->get( ActionRepository::class ),
				$c->get( WidgetRepository::class ),
				$c->get( ActionSanitizer::class ),
				$c->get( ActionTypeRegistry::class ),
				$c->get( RuntimeCompiler::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		// Rebuild the front-end snapshot whenever its inputs change.
		$rebuild = static function () use ( $container ) {
			$container->get( RuntimeCompiler::class )->rebuild();
		};
		add_action( 'bigloop_cb/widgets_changed', $rebuild );
		add_action( 'bigloop_cb/actions_changed', $rebuild );
		add_action( 'bigloop_cb/settings_updated', $rebuild );
		add_action( 'bigloop_cb/schedules_changed', array( ScheduleService::class, 'bump' ) );
		add_action( 'update_option_timezone_string', array( ScheduleService::class, 'bump' ) );
		add_action( 'update_option_gmt_offset', array( ScheduleService::class, 'bump' ) );

		// Built on init (not plugins_loaded): their dependencies translate labels, and WordPress 6.7+
		// requires translations to load at init or later.
		add_action(
			'init',
			static function () use ( $container ) {
				$container->get( FrontendController::class )->hooks();
				$container->get( Shortcode::class )->hooks();
				$container->get( Block::class )->hooks();
			},
			0
		);
		( new Elementor() )->hooks();

		add_action(
			'rest_api_init',
			static function () use ( $container ) {
				$container->get( WidgetsController::class )->register_routes();
				$container->get( ActionsController::class )->register_routes();
				$container->get( PresetsController::class )->register_routes();
				$container->get( SchedulesController::class )->register_routes();
				$container->get( LookupController::class )->register_routes();
			}
		);
	}
}
