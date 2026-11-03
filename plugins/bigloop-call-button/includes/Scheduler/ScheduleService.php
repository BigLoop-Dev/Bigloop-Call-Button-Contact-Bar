<?php
/**
 * Resolves schedules for rendering (site timezone, caching of computed windows).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Scheduler;

use Bigloop\CallButton\Repositories\ScheduleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * The front end embeds open windows for the next 14 days so a cached page still switches at the right moment
 * in the visitor's browser. Windows are recomputed at most once per hour per schedule.
 */
final class ScheduleService {

	public const HORIZON = 14 * DAY_IN_SECONDS;

	/**
	 * Repository.
	 *
	 * @var ScheduleRepository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param ScheduleRepository $repo Repository.
	 */
	public function __construct( ScheduleRepository $repo ) {
		$this->repo = $repo;
	}

	/**
	 * Invalidates cached windows (schedule edited, or the site timezone changed).
	 *
	 * @return void
	 */
	public static function bump(): void {
		update_option( 'blcb_sched_ver', (int) get_option( 'blcb_sched_ver', 0 ) + 1, true );
	}

	/**
	 * Schedule config with the timezone resolved ('' → the site's timezone).
	 *
	 * @param array<string,mixed> $config Stored config.
	 * @return array<string,mixed>
	 */
	public static function resolve( array $config ): array {
		if ( '' === (string) ( $config['timezone'] ?? '' ) ) {
			$config['timezone'] = wp_timezone_string();
		}
		return $config;
	}

	/**
	 * Open windows for a stored schedule from "now" (rounded down to the hour) for the next 14 days.
	 *
	 * @param int      $id  Schedule id.
	 * @param int|null $now Current time (tests).
	 * @return array{windows: array<int, array{0:int,1:int}>, open: bool}|null Null when the schedule does not exist.
	 */
	public function state( int $id, ?int $now = null ): ?array {
		$now  = $now ?? time();
		$hour = $now - ( $now % HOUR_IN_SECONDS );
		$key  = 'blcb_sched_' . $id . '_' . $hour . '_' . (int) get_option( 'blcb_sched_ver', 0 );

		$windows = get_transient( $key );
		if ( ! is_array( $windows ) ) {
			$doc = $this->repo->find( $id );
			if ( ! $doc ) {
				return null;
			}
			$windows = ScheduleEngine::windows( self::resolve( $doc['config'] ), $hour, $hour + self::HORIZON );
			set_transient( $key, $windows, HOUR_IN_SECONDS + 60 );
		}

		$open = false;
		foreach ( $windows as $w ) {
			if ( $now >= $w[0] && $now < $w[1] ) {
				$open = true;
				break;
			}
		}
		return array(
			'windows' => $windows,
			'open'    => $open,
		);
	}
}
