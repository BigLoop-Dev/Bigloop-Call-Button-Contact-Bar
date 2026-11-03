<?php
/**
 * Analytics endpoints: public click beacon, dashboard report, purge.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Analytics\Aggregator;
use Bigloop\CallButton\Analytics\Tracker;
use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Repositories\ActionRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   POST   /track       Public. Records one click (sent with navigator.sendBeacon). Always answers 204.
 *   GET    /analytics   Report for a local date range: totals, daily series, breakdowns.
 *   DELETE /analytics   Deletes all statistics.
 */
final class AnalyticsController extends Controller {

	/** Longest range a report may cover, in days. */
	public const MAX_RANGE = 731;

	/**
	 * Tracker.
	 *
	 * @var Tracker
	 */
	private $tracker;

	/**
	 * Stats.
	 *
	 * @var StatsReader
	 */
	private $stats;

	/**
	 * Aggregator.
	 *
	 * @var Aggregator
	 */
	private $aggregator;

	/**
	 * Widgets.
	 *
	 * @var WidgetRepository
	 */
	private $widgets;

	/**
	 * Actions.
	 *
	 * @var ActionRepository
	 */
	private $actions;

	/**
	 * Constructor.
	 *
	 * @param Tracker          $tracker    Tracker.
	 * @param StatsReader      $stats      Stats.
	 * @param Aggregator       $aggregator Aggregator.
	 * @param WidgetRepository $widgets    Widgets.
	 * @param ActionRepository $actions    Actions.
	 */
	public function __construct( Tracker $tracker, StatsReader $stats, Aggregator $aggregator, WidgetRepository $widgets, ActionRepository $actions ) {
		$this->tracker    = $tracker;
		$this->stats      = $stats;
		$this->aggregator = $aggregator;
		$this->widgets    = $widgets;
		$this->actions    = $actions;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$ns = RestServiceProvider::REST_NAMESPACE;
		register_rest_route(
			$ns,
			'/track',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'track' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			$ns,
			'/analytics',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'report' ),
					'permission_callback' => array( $this, 'can_view' ),
					'args'                => array(
						'from' => array(
							'type'    => 'string',
							'pattern' => '^\d{4}-\d{2}-\d{2}$',
						),
						'to'   => array(
							'type'    => 'string',
							'pattern' => '^\d{4}-\d{2}-\d{2}$',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'purge' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Permission for reading statistics (filterable capability).
	 *
	 * @return true|\WP_Error
	 */
	public function can_view() {
		/**
		 * Filters the capability needed to view analytics. Defaults to the plugin's management permission.
		 *
		 * @since 0.5.0
		 *
		 * @param string $cap Capability, or '' to use the management permission.
		 */
		$cap = (string) apply_filters( 'bigloop_cb/analytics_cap', '' );
		if ( '' !== $cap ) {
			return current_user_can( $cap ) ? true : new \WP_Error( 'blcb_forbidden', __( 'You are not allowed to view these statistics.', 'bigloop-call-button' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $this->can_manage();
	}

	/**
	 * Public click beacon.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function track( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			// sendBeacon may send text/plain; parse the raw body ourselves.
			$body = json_decode( (string) $request->get_body(), true );
		}
		$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$result = is_array( $body ) ? $this->tracker->record( $body, $ip ) : 'invalid';

		$response = new \WP_REST_Response( null, 204 );
		$response->header( 'X-Blcb-Track', $result );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Dashboard report.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function report( \WP_REST_Request $request ) {
		$today = StatsReader::today();
		$to    = (string) ( $request['to'] ?: $today );
		$from  = (string) ( $request['from'] ?: StatsReader::shift( $to, 29 ) );
		if ( ! $this->valid_date( $from ) || ! $this->valid_date( $to ) || $from > $to ) {
			return new \WP_Error( 'blcb_invalid_range', __( 'Choose a valid date range.', 'bigloop-call-button' ), array( 'status' => 400 ) );
		}
		$days = $this->days_between( $from, $to ) + 1;
		if ( $days > self::MAX_RANGE ) {
			return new \WP_Error( 'blcb_invalid_range', __( 'Choose a range of two years or less.', 'bigloop-call-button' ), array( 'status' => 400 ) );
		}

		// Catch up when WP-Cron has not run (low-traffic sites or DISABLE_WP_CRON), so reads stay fast.
		if ( $this->aggregator->is_behind() ) {
			$this->aggregator->run();
		}

		$rows     = $this->stats->rows( $from, $to );
		$prev_to  = StatsReader::shift( $from, 1 );
		$previous = 0;
		foreach ( $this->stats->rows( StatsReader::shift( $from, $days ), $prev_to ) as $row ) {
			$previous += $row['clicks'];
		}

		$series = array();
		for ( $d = $from; $d <= $to; $d = StatsReader::shift( $d, -1 ) ) {
			$series[ $d ] = 0;
		}
		$by_type   = array();
		$by_widget = array();
		$by_action = array();
		$by_device = array_fill_keys( array_values( StatsReader::DEVICES ), 0 );
		$total     = 0;
		foreach ( $rows as $row ) {
			$total                         += $row['clicks'];
			$series[ $row['day'] ]          = ( $series[ $row['day'] ] ?? 0 ) + $row['clicks'];
			$by_type[ $row['action_type'] ] = ( $by_type[ $row['action_type'] ] ?? 0 ) + $row['clicks'];
			$by_widget[ $row['widget_id'] ] = ( $by_widget[ $row['widget_id'] ] ?? 0 ) + $row['clicks'];
			$key                            = $row['action_id'];
			if ( ! isset( $by_action[ $key ] ) ) {
				$by_action[ $key ] = array(
					'clicks' => 0,
					'type'   => $row['action_type'],
				);
			}
			$by_action[ $key ]['clicks'] += $row['clicks'];
			$device                       = StatsReader::DEVICES[ $row['device'] ] ?? null;
			if ( $device ) {
				$by_device[ $device ] += $row['clicks'];
			}
		}
		arsort( $by_type );
		arsort( $by_widget );
		uasort(
			$by_action,
			static function ( $a, $b ) {
				return $b['clicks'] <=> $a['clicks'];
			}
		);

		$widgets = array();
		foreach ( $by_widget as $id => $clicks ) {
			$doc       = $this->widgets->find( (int) $id );
			$widgets[] = array(
				'id'      => (int) $id,
				'title'   => $doc ? (string) $doc['title'] : '',
				'deleted' => ! $doc,
				'clicks'  => $clicks,
			);
		}
		$actions = array();
		foreach ( array_slice( $by_action, 0, 20, true ) as $id => $info ) {
			$doc       = $this->actions->find( (int) $id );
			$actions[] = array(
				'id'          => (int) $id,
				'title'       => $doc ? (string) $doc['title'] : '',
				'deleted'     => ! $doc,
				'action_type' => $info['type'],
				'clicks'      => $info['clicks'],
			);
		}
		$types = array();
		foreach ( $by_type as $type => $clicks ) {
			$types[] = array(
				'action_type' => (string) $type,
				'clicks'      => $clicks,
			);
		}
		$pages = array();
		foreach ( $this->stats->top_pages( $from, $to, 10 ) as $page ) {
			$post    = get_post( $page['object_id'] );
			$pages[] = array(
				'id'     => $page['object_id'],
				'title'  => $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES ) : '',
				'url'    => $post ? (string) get_permalink( $post ) : '',
				'clicks' => $page['clicks'],
			);
		}
		$raw_since = $this->stats->pages_complete_since();

		$points = array();
		foreach ( $series as $day => $clicks ) {
			$points[] = array(
				'day'    => $day,
				'clicks' => $clicks,
			);
		}

		return new \WP_REST_Response(
			array(
				'from'      => $from,
				'to'        => $to,
				'days'      => $days,
				'timezone'  => wp_timezone_string(),
				'total'     => $total,
				'previous'  => $previous,
				'series'    => $points,
				'by_type'   => $types,
				'by_widget' => $widgets,
				'by_action' => $actions,
				'by_device' => $by_device,
				'pages'     => array(
					'items'          => $pages,
					// Page data comes from raw events, which retention removes after N days.
					'complete_since' => $raw_since,
				),
				'tracking'  => $this->tracker->enabled(),
			),
			200
		);
	}

	/**
	 * Deletes all statistics.
	 *
	 * @return \WP_REST_Response
	 */
	public function purge(): \WP_REST_Response {
		$this->aggregator->purge();
		return new \WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Strict Y-m-d check.
	 *
	 * @param string $date Date.
	 * @return bool
	 */
	private function valid_date( string $date ): bool {
		$dt = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Whole days from one date to another.
	 *
	 * @param string $from From.
	 * @param string $to   To.
	 * @return int
	 */
	private function days_between( string $from, string $to ): int {
		return (int) ( new \DateTimeImmutable( $from ) )->diff( new \DateTimeImmutable( $to ) )->days;
	}
}
