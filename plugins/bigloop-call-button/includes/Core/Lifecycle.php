<?php
/**
 * Activation, deactivation and multisite site creation.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

use Bigloop\CallButton\Database\Migrator;

defined( 'ABSPATH' ) || exit;

/**
 * Lifecycle handlers. These run outside the normal boot sequence, so they build what they need directly.
 */
final class Lifecycle {

	/** Option storing first install timestamp (used later for the review reminder). */
	public const INSTALLED_AT = 'blcb_installed_at';

	/** Cron hooks the plugin may schedule; cleared on deactivation. */
	public const CRON_HOOKS = array( 'blcb_daily_maintenance' );

	/**
	 * Activation handler.
	 *
	 * @param bool $network_wide Whether network-activated.
	 * @return void
	 */
	public static function activate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( self::site_ids() as $site_id ) {
				switch_to_blog( $site_id );
				self::install_site();
				restore_current_blog();
			}
			return;
		}
		self::install_site();
	}

	/**
	 * Deactivation handler. Never deletes data.
	 *
	 * @param bool $network_wide Whether network-deactivated.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( self::site_ids() as $site_id ) {
				switch_to_blog( $site_id );
				self::clear_cron();
				restore_current_blog();
			}
			return;
		}
		self::clear_cron();
	}

	/**
	 * Installs on a newly created site when the plugin is network-active.
	 *
	 * @param \WP_Site $site New site.
	 * @return void
	 */
	public static function on_new_site( $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( BLCB_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Installs or upgrades the current site.
	 *
	 * @return void
	 */
	private static function install_site(): void {
		global $wpdb;
		( new Migrator( $wpdb ) )->migrate();
		add_option( self::INSTALLED_AT, time(), '', false );
	}

	/**
	 * Unschedules plugin cron events.
	 *
	 * @return void
	 */
	private static function clear_cron(): void {
		foreach ( self::CRON_HOOKS as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * All site IDs on the network.
	 *
	 * @return int[]
	 */
	private static function site_ids(): array {
		return array_map(
			'intval',
			get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			)
		);
	}
}
