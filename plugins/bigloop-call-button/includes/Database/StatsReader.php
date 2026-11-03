<?php
/**
 * Read-side queries for click statistics.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Counts combine the daily aggregate table with raw events the roll-up has not processed yet.
 *
 * Days are the site's local calendar days. The roll-up job ({@see \Bigloop\CallButton\Analytics\Aggregator}) writes
 * daily rows and then advances the watermark option `blcb_stats_aggregated_until` (exclusive local date). Daily rows are
 * read for days before the watermark and raw events from the start of the watermark day onward, so nothing is double
 * counted or missed, however late the job runs. With no watermark yet, everything in both tables is counted.
 */
final class StatsReader {

	public const WATERMARK_OPTION = 'blcb_stats_aggregated_until';

	/** Device codes stored in the tables. */
	public const DEVICES = array(
		1 => 'desktop',
		2 => 'tablet',
		3 => 'mobile',
	);

	/**
	 * Database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb Database.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Aggregation watermark (Y-m-d local, exclusive) or null when nothing has been aggregated.
	 *
	 * @return string|null
	 */
	public function watermark(): ?string {
		$value = get_option( self::WATERMARK_OPTION, '' );
		return ( is_string( $value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) ? $value : null;
	}

	/**
	 * UTC "Y-m-d H:i:s" of local midnight starting a local date.
	 *
	 * @param string $date Local date (Y-m-d).
	 * @return string
	 */
	public static function utc_start( string $date ): string {
		$local = new \DateTimeImmutable( $date . ' 00:00:00', wp_timezone() );
		return $local->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Local date (Y-m-d) of a UTC "Y-m-d H:i[:s]" string.
	 *
	 * @param string $utc UTC timestamp string.
	 * @return string
	 */
	public static function local_day( string $utc ): string {
		$dt = new \DateTimeImmutable( strlen( $utc ) === 16 ? $utc . ':00' : $utc, new \DateTimeZone( 'UTC' ) );
		return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d' );
	}

	/**
	 * Today's local date.
	 *
	 * @return string
	 */
	public static function today(): string {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Local date N days before another.
	 *
	 * @param string $date Y-m-d.
	 * @param int    $days Days to subtract (negative adds).
	 * @return string
	 */
	public static function shift( string $date, int $days ): string {
		return ( new \DateTimeImmutable( $date, wp_timezone() ) )->modify( ( $days >= 0 ? '-' : '+' ) . abs( $days ) . ' days' )->format( 'Y-m-d' );
	}

	/**
	 * Click rows grouped by local day, widget, action and device for a local date range (inclusive).
	 *
	 * @param string $from First local day (Y-m-d).
	 * @param string $to   Last local day (Y-m-d).
	 * @return array<int, array{day:string, widget_id:int, action_id:int, action_type:string, device:int, clicks:int}>
	 */
	public function rows( string $from, string $to ): array {
		$daily  = Schema::stats_daily( $this->wpdb );
		$events = Schema::events( $this->wpdb );
		$mark   = $this->watermark();
		$end    = self::utc_start( self::shift( $to, -1 ) );

		// Daily rows: in range and (when aggregated) before the watermark.
		$daily_until = null === $mark ? '9999-12-31' : $mark;
		$wpdb        = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables, results are not cacheable.
		$agg = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT day, widget_id, action_id, action_type, device, SUM(clicks) AS clicks FROM %i
				WHERE day >= %s AND day <= %s AND day < %s
				GROUP BY day, widget_id, action_id, action_type, device',
				$daily,
				$from,
				$to,
				$daily_until
			),
			ARRAY_A
		);

		// Raw events from the later of the range start and the watermark day, bucketed by UTC minute so they can be
		// mapped to local days in PHP (works for any UTC offset, including half-hour zones).
		$raw_from = self::utc_start( null !== $mark && $mark > $from ? $mark : $from );
		$raw      = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT SUBSTR(created_at, 1, 16) AS minute, widget_id, action_id, action_type, device, COUNT(*) AS clicks FROM %i
				WHERE created_at >= %s AND created_at < %s
				GROUP BY SUBSTR(created_at, 1, 16), widget_id, action_id, action_type, device',
				$events,
				$raw_from,
				$end
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		$add = static function ( string $day, array $row ) use ( &$out ) {
			$key = $day . '|' . $row['widget_id'] . '|' . $row['action_id'] . '|' . $row['device'];
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = array(
					'day'         => $day,
					'widget_id'   => (int) $row['widget_id'],
					'action_id'   => (int) $row['action_id'],
					'action_type' => (string) $row['action_type'],
					'device'      => (int) $row['device'],
					'clicks'      => 0,
				);
			}
			$out[ $key ]['clicks'] += (int) $row['clicks'];
		};
		foreach ( $agg as $row ) {
			$add( (string) $row['day'], $row );
		}
		foreach ( $raw as $row ) {
			$add( self::local_day( (string) $row['minute'] ), $row );
		}
		return array_values( $out );
	}

	/**
	 * Total clicks, all time.
	 *
	 * @return int
	 */
	public function total_clicks(): int {
		$daily  = Schema::stats_daily( $this->wpdb );
		$events = Schema::events( $this->wpdb );
		$mark   = $this->watermark();

		$until = $mark ?? '9999-12-31';
		$since = null === $mark ? '1000-01-01 00:00:00' : self::utc_start( $mark );
		$wpdb  = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables, results are not cacheable.
		$aggregated = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(clicks),0) FROM %i WHERE day < %s', $daily, $until ) );
		$pending    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE created_at >= %s', $events, $since ) );
		// phpcs:enable

		return $aggregated + $pending;
	}

	/**
	 * Clicks per action type within the last N days (including today), most clicked first.
	 *
	 * @param int $days Window in days.
	 * @return array<int, array{action_type:string, clicks:int}>
	 */
	public function clicks_by_action_type( int $days = 30 ): array {
		$today = self::today();
		$sum   = array();
		foreach ( $this->rows( self::shift( $today, max( 1, $days ) - 1 ), $today ) as $row ) {
			$sum[ $row['action_type'] ] = ( $sum[ $row['action_type'] ] ?? 0 ) + $row['clicks'];
		}
		arsort( $sum );
		$out = array();
		foreach ( $sum as $type => $clicks ) {
			$out[] = array(
				'action_type' => (string) $type,
				'clicks'      => (int) $clicks,
			);
		}
		return $out;
	}

	/**
	 * All-time clicks per widget id.
	 *
	 * @return array<int,int>
	 */
	public function clicks_by_widget(): array {
		$daily  = Schema::stats_daily( $this->wpdb );
		$events = Schema::events( $this->wpdb );
		$mark   = $this->watermark();

		$until = $mark ?? '9999-12-31';
		$since = null === $mark ? '1000-01-01 00:00:00' : self::utc_start( $mark );
		$wpdb  = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom tables, results are not cacheable.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT widget_id, SUM(clicks) AS clicks FROM (
					SELECT widget_id, clicks FROM %i WHERE day < %s
					UNION ALL
					SELECT widget_id, 1 AS clicks FROM %i WHERE created_at >= %s
				) t GROUP BY widget_id',
				$daily,
				$until,
				$events,
				$since
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row['widget_id'] ] = (int) $row['clicks'];
		}
		return $out;
	}

	/**
	 * Most recent raw click events.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array{widget_id:int, action_id:int, action_type:string, device:int, object_id:int, created_at:string}>
	 */
	public function recent_events( int $limit = 8 ): array {
		$events = Schema::events( $this->wpdb );
		$limit  = max( 1, min( 50, $limit ) );
		$wpdb   = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, results are not cacheable.
		$rows = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT widget_id, action_id, action_type, device, object_id, created_at FROM %i ORDER BY id DESC LIMIT %d', $events, $limit ), ARRAY_A );

		return array_map(
			static function ( $row ) {
				return array(
					'widget_id'   => (int) $row['widget_id'],
					'action_id'   => (int) $row['action_id'],
					'action_type' => (string) $row['action_type'],
					'device'      => (int) $row['device'],
					'object_id'   => (int) $row['object_id'],
					'created_at'  => (string) $row['created_at'],
				);
			},
			$rows
		);
	}

	/**
	 * Clicks per page/post (raw events only, since daily rows do not keep the page) within a local date range.
	 *
	 * @param string $from  First local day.
	 * @param string $to    Last local day.
	 * @param int    $limit Max rows.
	 * @return array<int, array{object_id:int, clicks:int}>
	 */
	public function top_pages( string $from, string $to, int $limit = 10 ): array {
		$events = Schema::events( $this->wpdb );
		$start  = self::utc_start( $from );
		$end    = self::utc_start( self::shift( $to, -1 ) );
		$limit  = max( 1, min( 50, $limit ) );
		$wpdb   = $this->wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- custom table, results are not cacheable.
		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				'SELECT object_id, COUNT(*) AS clicks FROM %i
				WHERE created_at >= %s AND created_at < %s AND object_id IS NOT NULL AND object_id > 0
				GROUP BY object_id ORDER BY clicks DESC LIMIT %d',
				$events,
				$start,
				$end,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map(
			static function ( $row ) {
				return array(
					'object_id' => (int) $row['object_id'],
					'clicks'    => (int) $row['clicks'],
				);
			},
			$rows
		);
	}

	/**
	 * First local day of complete page data when retention has removed older detailed records, else null.
	 *
	 * @return string|null
	 */
	public function pages_complete_since(): ?string {
		$since = $this->raw_since();
		$daily = Schema::stats_daily( $this->wpdb );
		$until = $since ?? '9999-12-31';
		$wpdb  = $this->wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, results are not cacheable.
		$older = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(day) FROM %i WHERE day < %s', $daily, $until ) );
		return $older ? ( $since ?? self::today() ) : null;
	}

	/**
	 * Earliest local day for which raw events (with page data) still exist, or null.
	 *
	 * @return string|null
	 */
	public function raw_since(): ?string {
		$events = Schema::events( $this->wpdb );
		$wpdb   = $this->wpdb;
		$first  = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(created_at) FROM %i', $events ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- custom table, not cacheable.
		return $first ? self::local_day( (string) $first ) : null;
	}
}
