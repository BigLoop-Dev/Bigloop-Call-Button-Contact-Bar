<?php
/**
 * Base REST controller.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Core\Permissions;

defined( 'ABSPATH' ) || exit;

/**
 * Shared permission check and helpers.
 */
abstract class Controller {

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	abstract public function register_routes(): void;

	/**
	 * Permission callback for management endpoints. Cookie auth requires the wp_rest nonce,
	 * which WordPress core validates before this runs.
	 *
	 * @return true|\WP_Error
	 */
	public function can_manage() {
		if ( Permissions::current_user_can_manage() ) {
			return true;
		}
		return new \WP_Error(
			'blcb_forbidden',
			__( 'You are not allowed to manage Bigloop Contact.', 'bigloop-call-button' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
