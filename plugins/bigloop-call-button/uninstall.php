<?php
/**
 * Uninstall handler. Data is removed only when the site owner opted in
 * (Settings → General → "Remove all data when the plugin is deleted").
 *
 * @package Bigloop\CallButton
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes all plugin data for the current site if the owner opted in.
 *
 * @return void
 */
function blcb_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'blcb_settings', array() );
	if ( empty( $settings['general']['delete_data_on_uninstall'] ) ) {
		return;
	}

	$post_types = array( 'blcb_widget', 'blcb_action', 'blcb_schedule', 'blcb_preset' );
	foreach ( $post_types as $post_type ) {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	foreach ( array( 'blcb_events', 'blcb_stats_daily' ) as $table ) {
		$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $table ) . '`' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared -- fixed table names.
	}

	// Delete through the options API so object caches (including persistent ones) stay consistent.
	$like    = $wpdb->esc_like( 'blcb_' ) . '%';
	$options = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $options as $option ) {
		delete_option( $option );
	}
	// Transients (schedule windows, rate limits, locks).
	$transients = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_blcb_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $transients as $option ) {
		delete_transient( substr( $option, strlen( '_transient_' ) ) );
	}
	delete_metadata( 'user', 0, 'blcb_dismissed_notices', '', true );

	wp_clear_scheduled_hook( 'blcb_daily_maintenance' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $blcb_site_id ) {
		switch_to_blog( (int) $blcb_site_id );
		blcb_uninstall_site();
		restore_current_blog();
	}
} else {
	blcb_uninstall_site();
}
