<?php
/**
 * Plugin kernel.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

use Bigloop\CallButton\Admin\AdminServiceProvider;
use Bigloop\CallButton\Analytics\AnalyticsServiceProvider;
use Bigloop\CallButton\API\Api;
use Bigloop\CallButton\REST\RestServiceProvider;
use Bigloop\CallButton\Widgets\WidgetsServiceProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Boots service providers and exposes the container.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Booted providers.
	 *
	 * @var ServiceProvider[]
	 */
	private $providers = array();

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->container->instance( self::class, $this );
	}

	/**
	 * Returns the singleton.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Entry point on plugins_loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		self::instance()->run();
	}

	/**
	 * Registers and boots all providers, then announces the public API.
	 *
	 * @return void
	 */
	private function run(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		$providers = array(
			new CoreServiceProvider(),
			new RestServiceProvider(),
			new WidgetsServiceProvider(),
			new AnalyticsServiceProvider(),
			new AdminServiceProvider(),
		);

		/**
		 * Filters the service providers booted by the plugin.
		 *
		 * Extensions may append their own ServiceProvider instances.
		 *
		 * @since 0.1.0
		 *
		 * @param ServiceProvider[] $providers Providers.
		 */
		$providers = apply_filters( 'bigloop_cb/service_providers', $providers );

		foreach ( $providers as $provider ) {
			if ( $provider instanceof ServiceProvider ) {
				$provider->register( $this->container );
				$this->providers[] = $provider;
			}
		}
		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		/**
		 * Fires once the plugin is fully loaded. This is the integration point for Pro and add-ons.
		 *
		 * @since 0.1.0
		 *
		 * @param Api $api Versioned public API.
		 */
		do_action( 'bigloop_cb/loaded', $this->container->get( Api::class ) );
	}

	/**
	 * Container accessor.
	 *
	 * @return Container
	 */
	public function container(): Container {
		return $this->container;
	}
}
