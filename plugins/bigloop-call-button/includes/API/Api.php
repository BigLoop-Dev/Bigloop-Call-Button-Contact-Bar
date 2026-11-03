<?php
/**
 * Versioned public PHP API for Pro and third-party extensions.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\API;

use Bigloop\CallButton\Core\Container;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Stable facade. Extensions must use this object (received via the `bigloop_cb/loaded` action)
 * instead of internal classes. Follows semantic versioning; breaking changes bump the major version.
 *
 * Example:
 *     add_action( 'bigloop_cb/loaded', function ( $api ) {
 *         if ( version_compare( $api->version(), '1.0.0', '<' ) ) { return; }
 *         // register extensions…
 *     } );
 */
final class Api {

	/** Public API version (independent of the plugin version). */
	public const VERSION = '1.1.0';

	/**
	 * Container.
	 *
	 * @var Container
	 */
	private $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Public API version.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	public function version(): string {
		return self::VERSION;
	}

	/**
	 * Installed plugin version.
	 *
	 * @since 0.1.0
	 * @return string
	 */
	public function plugin_version(): string {
		return BLCB_VERSION;
	}

	/**
	 * Read-only settings access.
	 *
	 * @since 0.1.0
	 *
	 * @param string $path    "section.key".
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function setting( string $path, $default = null ) {
		return $this->container->get( Settings::class )->get( $path, $default );
	}

	/**
	 * Whether the current user may manage the plugin (same rule as Free's own screens).
	 *
	 * @since 1.1.0 (plugin 0.6.0)
	 */
	public function can_manage(): bool {
		return \Bigloop\CallButton\Core\Permissions::current_user_can_manage();
	}

	/**
	 * Free's REST namespace.
	 *
	 * @since 1.1.0 (plugin 0.6.0)
	 */
	public function rest_namespace(): string {
		return \Bigloop\CallButton\REST\RestServiceProvider::REST_NAMESPACE;
	}

	/**
	 * Widget summaries: id, title, enabled, type.
	 *
	 * @since 1.1.0 (plugin 0.6.0)
	 * @return array<int,array{id:int,title:string,enabled:bool,type:string}>
	 */
	public function widgets(): array {
		return $this->summaries( \Bigloop\CallButton\Repositories\WidgetRepository::class );
	}

	/**
	 * Action summaries: id, title, enabled, type.
	 *
	 * @since 1.1.0 (plugin 0.6.0)
	 * @return array<int,array{id:int,title:string,enabled:bool,type:string}>
	 */
	public function actions(): array {
		return $this->summaries( \Bigloop\CallButton\Repositories\ActionRepository::class );
	}

	/**
	 * Summaries from a repository.
	 *
	 * @param string $class Repository class.
	 * @return array<int,array{id:int,title:string,enabled:bool,type:string}>
	 */
	private function summaries( string $class ): array {
		$out = array();
		$res = $this->container->get( $class )->list( array( 'per_page' => 0 ) );
		foreach ( (array) $res['items'] as $row ) {
			$out[] = array(
				'id'      => (int) $row['id'],
				'title'   => (string) $row['title'],
				'enabled' => (bool) $row['enabled'],
				'type'    => (string) ( $row['type'] ?? '' ),
			);
		}
		return $out;
	}
}
