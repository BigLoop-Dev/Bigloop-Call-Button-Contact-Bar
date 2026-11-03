<?php
/**
 * Small dependency-injection container.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lazily builds and caches shared services by ID.
 */
final class Container {

	/**
	 * Service factories keyed by ID.
	 *
	 * @var array<string, callable>
	 */
	private $factories = array();

	/**
	 * Resolved shared instances keyed by ID.
	 *
	 * @var array<string, mixed>
	 */
	private $instances = array();

	/**
	 * IDs currently being resolved (circular dependency guard).
	 *
	 * @var array<string, bool>
	 */
	private $resolving = array();

	/**
	 * Registers a shared service factory. The factory receives the container.
	 *
	 * @param string   $id      Service ID (usually a class name).
	 * @param callable $factory Factory.
	 * @return void
	 */
	public function set( string $id, callable $factory ): void {
		$this->factories[ $id ] = $factory;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Registers an already-built instance.
	 *
	 * @param string $id       Service ID.
	 * @param mixed  $instance Instance.
	 * @return void
	 */
	public function instance( string $id, $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether a service is registered.
	 *
	 * @param string $id Service ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->factories[ $id ] ) || array_key_exists( $id, $this->instances );
	}

	/**
	 * Resolves a service.
	 *
	 * @param string $id Service ID.
	 * @return mixed
	 * @throws \RuntimeException When the service is unknown or circular.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}
		if ( ! isset( $this->factories[ $id ] ) ) {
			throw new \RuntimeException( sprintf( 'Service "%s" is not registered.', esc_html( $id ) ) );
		}
		if ( isset( $this->resolving[ $id ] ) ) {
			throw new \RuntimeException( sprintf( 'Circular dependency while resolving "%s".', esc_html( $id ) ) );
		}
		$this->resolving[ $id ] = true;
		try {
			$this->instances[ $id ] = ( $this->factories[ $id ] )( $this );
		} finally {
			unset( $this->resolving[ $id ] );
		}
		return $this->instances[ $id ];
	}
}
