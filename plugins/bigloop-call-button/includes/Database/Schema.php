<?php
/**
 * Table names.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Central source of custom table names (per site prefix).
 */
final class Schema {

	/**
	 * Raw click events table.
	 *
	 * @param \wpdb $wpdb Database.
	 * @return string
	 */
	public static function events( $wpdb ): string {
		return $wpdb->prefix . 'blcb_events';
	}

	/**
	 * Daily aggregate stats table.
	 *
	 * @param \wpdb $wpdb Database.
	 * @return string
	 */
	public static function stats_daily( $wpdb ): string {
		return $wpdb->prefix . 'blcb_stats_daily';
	}

	/**
	 * All custom tables (used by uninstall).
	 *
	 * @param \wpdb $wpdb Database.
	 * @return string[]
	 */
	public static function all( $wpdb ): array {
		return array( self::events( $wpdb ), self::stats_daily( $wpdb ) );
	}
}
