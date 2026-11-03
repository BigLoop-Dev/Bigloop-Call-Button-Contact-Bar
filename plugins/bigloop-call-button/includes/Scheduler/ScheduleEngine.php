<?php
/**
 * Schedule evaluation: turns a schedule document into UTC open windows.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Pure, dependency-free. Schedule document:
 *
 *   {
 *     timezone: 'Europe/London',                  // resolved IANA zone (callers substitute the site zone for '')
 *     weekly:   { mon: [ ['09:00','17:00'] ], … sun: [] },   // end <= start means the interval runs past midnight
 *     date_range: { from: 'Y-m-d'|'', to: 'Y-m-d'|'' },      // optional: schedule only active within this range
 *     holidays: [ { from: 'Y-m-d', to: 'Y-m-d', label } ],   // closed all day (inclusive)
 *     special:  [ { date: 'Y-m-d', intervals: [ … ], label } ] // replaces the weekly hours for that date ([] = closed)
 *   }
 *
 * Daylight-saving changes are handled by building local times in the schedule's timezone.
 */
final class ScheduleEngine {

	public const DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * Open windows (UTC epoch seconds) intersecting [from, to), merged and sorted.
	 *
	 * @param array<string,mixed> $schedule Schedule.
	 * @param int                 $from     Start (epoch).
	 * @param int                 $to       End (epoch).
	 * @return array<int, array{0:int,1:int}>
	 */
	public static function windows( array $schedule, int $from, int $to ): array {
		$tz = self::zone( (string) ( $schedule['timezone'] ?? 'UTC' ) );

		// Start one local day early so intervals running past midnight into the range are included.
		$day  = ( new \DateTimeImmutable( '@' . $from ) )->setTimezone( $tz )->setTime( 0, 0 )->modify( '-1 day' );
		$last = ( new \DateTimeImmutable( '@' . $to ) )->setTimezone( $tz )->setTime( 0, 0 );

		$windows = array();
		for ( $guard = 0; $day <= $last && $guard < 800; $guard++, $day = $day->modify( '+1 day' ) ) {
			foreach ( self::intervals_for( $schedule, $day ) as $interval ) {
				$start = self::at( $day, $interval[0] );
				$end   = self::at( $day, $interval[1] );
				if ( null === $start || null === $end ) {
					continue;
				}
				if ( $end <= $start ) {
					$end = self::at( $day->modify( '+1 day' ), $interval[1] );
				}
				$s = max( $start->getTimestamp(), $from );
				$e = min( $end->getTimestamp(), $to );
				if ( $e > $s ) {
					$windows[] = array( $s, $e );
				}
			}
		}
		return self::merge( $windows );
	}

	/**
	 * Whether the schedule is open at a moment.
	 *
	 * @param array<string,mixed> $schedule Schedule.
	 * @param int                 $ts       Epoch.
	 * @return bool
	 */
	public static function is_open( array $schedule, int $ts ): bool {
		foreach ( self::windows( $schedule, $ts, $ts + 1 ) as $w ) {
			if ( $ts >= $w[0] && $ts < $w[1] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Intervals that apply to one local date.
	 *
	 * @param array<string,mixed> $schedule Schedule.
	 * @param \DateTimeImmutable  $day      Local midnight.
	 * @return array<int, array{0:string,1:string}>
	 */
	public static function intervals_for( array $schedule, \DateTimeImmutable $day ): array {
		$date  = $day->format( 'Y-m-d' );
		$range = (array) ( $schedule['date_range'] ?? array() );
		if ( ( '' !== (string) ( $range['from'] ?? '' ) && $date < $range['from'] ) || ( '' !== (string) ( $range['to'] ?? '' ) && $date > $range['to'] ) ) {
			return array();
		}
		foreach ( (array) ( $schedule['holidays'] ?? array() ) as $h ) {
			$hf = (string) ( $h['from'] ?? '' );
			$ht = (string) ( $h['to'] ?? '' ) ?: $hf;
			if ( '' !== $hf && $date >= $hf && $date <= $ht ) {
				return array();
			}
		}
		foreach ( (array) ( $schedule['special'] ?? array() ) as $sp ) {
			if ( ( $sp['date'] ?? '' ) === $date ) {
				return (array) ( $sp['intervals'] ?? array() );
			}
		}
		$dow = self::DAYS[ (int) $day->format( 'N' ) - 1 ];
		return (array) ( $schedule['weekly'][ $dow ] ?? array() );
	}

	/**
	 * Local time on a date ('24:00' = next midnight). Returns null for malformed times.
	 *
	 * @param \DateTimeImmutable $day  Local midnight.
	 * @param string             $time HH:MM.
	 * @return \DateTimeImmutable|null
	 */
	private static function at( \DateTimeImmutable $day, string $time ): ?\DateTimeImmutable {
		if ( ! preg_match( '/^([01]\d|2[0-4]):([0-5]\d)$/', $time, $m ) ) {
			return null;
		}
		if ( '24' === $m[1] ) {
			return $day->modify( '+1 day' )->setTime( 0, 0 );
		}
		return $day->setTime( (int) $m[1], (int) $m[2] );
	}

	/**
	 * Merges overlapping/adjacent windows.
	 *
	 * @param array<int, array{0:int,1:int}> $windows Windows.
	 * @return array<int, array{0:int,1:int}>
	 */
	private static function merge( array $windows ): array {
		usort(
			$windows,
			static function ( $a, $b ) {
				return $a[0] <=> $b[0];
			}
		);
		$out = array();
		foreach ( $windows as $w ) {
			$n = count( $out );
			if ( $n && $w[0] <= $out[ $n - 1 ][1] ) {
				$out[ $n - 1 ][1] = max( $out[ $n - 1 ][1], $w[1] );
			} else {
				$out[] = $w;
			}
		}
		return $out;
	}

	/**
	 * Timezone object (UTC when invalid).
	 *
	 * @param string $name IANA name or ±HH:MM offset.
	 * @return \DateTimeZone
	 */
	public static function zone( string $name ): \DateTimeZone {
		try {
			return new \DateTimeZone( '' !== $name ? $name : 'UTC' );
		} catch ( \Exception $e ) {
			return new \DateTimeZone( 'UTC' );
		}
	}
}
