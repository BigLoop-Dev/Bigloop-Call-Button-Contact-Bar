<?php
/**
 * Daily roll-up and retention for click statistics.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Analytics;

use Bigloop\CallButton\Database\Schema;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Rolls raw click events up into `blcb_stats_daily`, one complete local day at a time, then advances the watermark
 * ({@see StatsReader::WATERMARK_OPTION}). Each day is rewritten (delete + insert) before the watermark moves, so a run
 * interrupted half-way is simply repeated next time without double counting.
 *
 * Raw events older than the retention period are deleted, but never events the roll-up has not processed yet.
 */
final class Aggregator {

	public const CRON_HOOK = 'blcb_daily_maintenance';

	/** Days processed per run; a follow-up run is scheduled when more remain. */
	public const BATCH_DAYS = 60;

	/**
	 * Database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Stats.
	 *
	 * @var StatsReader
	 */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param \wpdb       $wpdb     Database.
	 * @param Settings    $settings Settings.
	 * @param StatsReader $stats    Stats.
	 */
	public function __construct( $wpdb, Settings $settings, StatsReader $stats ) {
		$this->wpdb     = $wpdb;
		$this->settings = $settings;
		$this->stats    = $stats;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
	}

	/**
	 * Schedules the daily job if missing (cheap: reads the autoloaded cron option).
	 *
	 * @return void
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// Shortly after 03:00 site time, when traffic is usually low.
			$next = ( new \DateTimeImmutable( 'tomorrow 03:10', wp_timezone() ) )->getTimestamp();
			wp_schedule_event( $next, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Whether complete days are waiting to be rolled up.
	 *
	 * @return bool
	 */
	public function is_behind(): bool {
		$mark = $this->stats->watermark();
		if ( null === $mark ) {
			$first = $this->stats->raw_since();
			return null !== $first && $first < StatsReader::today();
		}
		return $mark < StatsReader::today();
	}

	/**
	 * Rolls up complete days and applies retention.
	 *
	 * @param int $max_days Max days to process in this run.
	 * @return array{days:int, deleted:int, remaining:bool}
	 */
	public function run( $max_days = self::BATCH_DAYS ): array {
		$max_days = max( 1, (int) $max_days );
		$lock     = 'blcb_aggregating';
		if ( get_transient( $lock ) ) {
			return array(
				'days'      => 0,
				'deleted'   => 0,
				'remaining' => true,
			);
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		$daily  = Schema::stats_daily( $this->wpdb );
		$events = Schema::events( $this->wpdb );
		$today  = StatsReader::today();
		$day    = $this->stats->watermark() ?? $this->stats->raw_since() ?? $today;
		$done   = 0;

		$wpdb = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables; writes are not cacheable.
		while ( $day < $today && $done < $max_days ) {
			$next  = StatsReader::shift( $day, -1 );
			$start = StatsReader::utc_start( $day );
			$end   = StatsReader::utc_start( $next );
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE day = %s', $daily, $day ) );
			$wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (day, widget_id, action_id, action_type, device, clicks)
					SELECT %s, widget_id, action_id, MAX(action_type), device, COUNT(*) FROM %i
					WHERE created_at >= %s AND created_at < %s
					GROUP BY widget_id, action_id, device',
					$daily,
					$day,
					$events,
					$start,
					$end
				)
			);
			update_option( StatsReader::WATERMARK_OPTION, $next, false );
			$day = $next;
			++$done;
		}

		// Retention: raw events older than N days, and never past the watermark.
		$deleted = 0;
		$mark    = $this->stats->watermark();
		if ( null !== $mark ) {
			$keep_from = StatsReader::shift( $today, (int) $this->settings->get( 'analytics.retention_days', 90 ) );
			$cutoff    = min( $keep_from, $mark );
			$before    = StatsReader::utc_start( $cutoff );
			$deleted   = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s', $events, $before ) );
		}
		// phpcs:enable

		delete_transient( $lock );

		$remaining = $day < $today;
		if ( $remaining ) {
			// WordPress ignores this when a run of the same hook is already due within 10 minutes.
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK );
		}

		/**
		 * Fires after the analytics roll-up.
		 *
		 * @since 0.5.0
		 *
		 * @param int  $days      Days rolled up.
		 * @param int  $deleted   Raw events deleted by retention.
		 * @param bool $remaining Whether more days remain.
		 */
		do_action( 'bigloop_cb/analytics_aggregated', $done, $deleted, $remaining );

		return array(
			'days'      => $done,
			'deleted'   => $deleted,
			'remaining' => $remaining,
		);
	}

	/**
	 * Deletes all statistics.
	 *
	 * @return void
	 */
	public function purge(): void {
		$wpdb = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables; writes are not cacheable.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Schema::events( $wpdb ) ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Schema::stats_daily( $wpdb ) ) );
		// phpcs:enable
		delete_option( StatsReader::WATERMARK_OPTION );

		/**
		 * Fires after all statistics are deleted.
		 *
		 * @since 0.5.0
		 */
		do_action( 'bigloop_cb/analytics_purged' );
	}
}
