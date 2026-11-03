<?php
/**
 * Migration contract.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Database;

defined( 'ABSPATH' ) || exit;

/**
 * One forward-only schema/data step. Migrations must be idempotent.
 */
interface Migration {

	/**
	 * Sequential schema version this migration brings the site to.
	 *
	 * @return int
	 */
	public function version(): int;

	/**
	 * Applies the migration.
	 *
	 * @param \wpdb $wpdb Database.
	 * @return void
	 */
	public function up( $wpdb ): void;
}
