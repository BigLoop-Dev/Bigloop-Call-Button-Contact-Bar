<?php
/**
 * Environment requirement checks. Must stay PHP 5.6-parseable so old hosts get a notice, not a fatal.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Checks PHP and WordPress versions before anything else loads.
 */
final class Requirements {

	/**
	 * Whether the environment satisfies minimum versions.
	 *
	 * @return bool
	 */
	public static function met() {
		return version_compare( PHP_VERSION, BLCB_MIN_PHP, '>=' )
			&& version_compare( get_bloginfo( 'version' ), BLCB_MIN_WP, '>=' );
	}

	/**
	 * Shows an admin notice explaining which requirement failed.
	 *
	 * @return void
	 */
	public static function register_notice() {
		add_action(
			'admin_notices',
			function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf(
					'<div class="notice notice-error"><p>%s</p></div>',
					esc_html(
						sprintf(
							/* translators: 1: required PHP version, 2: required WordPress version, 3: current PHP version, 4: current WordPress version. */
							__( 'Bigloop Call Button & Contact Bar requires PHP %1$s+ and WordPress %2$s+. This site runs PHP %3$s and WordPress %4$s. The plugin is inactive until the site is updated.', 'bigloop-call-button' ),
							BLCB_MIN_PHP,
							BLCB_MIN_WP,
							PHP_VERSION,
							get_bloginfo( 'version' )
						)
					)
				);
			}
		);
	}
}
