<?php
/**
 * Service provider contract.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

defined( 'ABSPATH' ) || exit;

/**
 * A module registers its services in register() and attaches WordPress hooks in boot().
 */
interface ServiceProvider {

	/**
	 * Registers services in the container. Must not touch WordPress hooks.
	 *
	 * @param Container $container Container.
	 * @return void
	 */
	public function register( Container $container ): void;

	/**
	 * Attaches hooks. Called after every provider has registered.
	 *
	 * @param Container $container Container.
	 * @return void
	 */
	public function boot( Container $container ): void;
}
