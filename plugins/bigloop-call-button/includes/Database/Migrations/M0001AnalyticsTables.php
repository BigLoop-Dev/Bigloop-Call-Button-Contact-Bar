<?php
/**
 * Creates the analytics tables.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Database\Migrations;

use Bigloop\CallButton\Database\Migration;
use Bigloop\CallButton\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Raw click events + daily aggregates. No IPs, user agents or user IDs are stored.
 */
final class M0001AnalyticsTables implements Migration {

	/**
	 * {@inheritDoc}
	 */
	public function version(): int {
		return 1;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \wpdb $wpdb Database.
	 */
	public function up( $wpdb ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$events  = Schema::events( $wpdb );
		$daily   = Schema::stats_daily( $wpdb );

		// dbDelta formatting rules: two spaces after PRIMARY KEY, one field per line.
		dbDelta(
			"CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  widget_id bigint(20) unsigned NOT NULL,
  action_id bigint(20) unsigned NOT NULL,
  action_type varchar(32) NOT NULL,
  device tinyint(3) unsigned NOT NULL DEFAULT 0,
  object_id bigint(20) unsigned DEFAULT NULL,
  variant varchar(16) DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY widget_created (widget_id,created_at)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$daily} (
  day date NOT NULL,
  widget_id bigint(20) unsigned NOT NULL,
  action_id bigint(20) unsigned NOT NULL,
  action_type varchar(32) NOT NULL,
  device tinyint(3) unsigned NOT NULL DEFAULT 0,
  clicks int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (day,widget_id,action_id,device),
  KEY action_type_day (action_type,day)
) {$charset};"
		);
	}
}
