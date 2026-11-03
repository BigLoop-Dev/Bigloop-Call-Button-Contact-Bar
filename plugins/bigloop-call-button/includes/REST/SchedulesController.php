<?php
/**
 * Schedule endpoints.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Domain\ScheduleSanitizer;
use Bigloop\CallButton\Repositories\ScheduleRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;
use Bigloop\CallButton\Scheduler\ScheduleEngine;
use Bigloop\CallButton\Scheduler\ScheduleService;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   GET  /schedules               List with "open now" status and usage.
 *   POST /schedules               Create.
 *   GET|PUT|DELETE /schedules/{id}
 *   POST /schedules/{id}/duplicate
 *   POST /schedules/preview       Dry run: { config } → open now, next change, the next 7 days of hours.
 *   GET  /schedules/meta          Timezones and the site timezone.
 */
final class SchedulesController extends Controller {

	/**
	 * Schedules.
	 *
	 * @var ScheduleRepository
	 */
	private $schedules;

	/**
	 * Widgets (usage).
	 *
	 * @var WidgetRepository
	 */
	private $widgets;

	/**
	 * Sanitizer.
	 *
	 * @var ScheduleSanitizer
	 */
	private $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param ScheduleRepository $schedules Schedules.
	 * @param WidgetRepository   $widgets   Widgets.
	 * @param ScheduleSanitizer  $sanitizer Sanitizer.
	 */
	public function __construct( ScheduleRepository $schedules, WidgetRepository $widgets, ScheduleSanitizer $sanitizer ) {
		$this->schedules = $schedules;
		$this->widgets   = $widgets;
		$this->sanitizer = $sanitizer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$ns   = RestServiceProvider::REST_NAMESPACE;
		$perm = array( $this, 'can_manage' );
		register_rest_route(
			$ns,
			'/schedules',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $perm,
				),
			)
		);
		register_rest_route(
			$ns,
			'/schedules/meta',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'meta' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/schedules/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/schedules/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update' ),
					'permission_callback' => $perm,
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'destroy' ),
					'permission_callback' => $perm,
				),
			)
		);
		register_rest_route(
			$ns,
			'/schedules/(?P<id>\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * Lists schedules.
	 *
	 * @return \WP_REST_Response
	 */
	public function index(): \WP_REST_Response {
		$usage = $this->usage();
		$items = array_map(
			function ( $doc ) use ( $usage ) {
				return $this->present( $doc, $usage[ $doc['id'] ] ?? array() );
			},
			$this->schedules->list( array( 'orderby' => 'title' ) )['items']
		);
		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * Timezones.
	 *
	 * @return \WP_REST_Response
	 */
	public function meta(): \WP_REST_Response {
		return rest_ensure_response(
			array(
				'site_timezone' => wp_timezone_string(),
				'timezones'     => \DateTimeZone::listIdentifiers(),
			)
		);
	}

	/**
	 * One schedule.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$doc = $this->schedules->find( (int) $request['id'] );
		return $doc ? rest_ensure_response( $this->present( $doc, $this->usage()[ $doc['id'] ] ?? array() ) ) : $this->not_found();
	}

	/**
	 * Creates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		$result = $this->sanitizer->sanitize( $request->get_json_params() );
		if ( $result['errors'] ) {
			return $this->invalid( $result['errors'] );
		}
		$id = $this->schedules->create( $result['data'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->schedules->find( $id ), array() ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Updates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->schedules->exists( $id ) ) {
			return $this->not_found();
		}
		$result = $this->sanitizer->sanitize( $request->get_json_params() );
		if ( $result['errors'] ) {
			return $this->invalid( $result['errors'] );
		}
		$this->schedules->update( $id, $result['data'] );
		return rest_ensure_response( $this->present( (array) $this->schedules->find( $id ), $this->usage()[ $id ] ?? array() ) );
	}

	/**
	 * Deletes. Widgets that used it stop following a schedule (and are always shown).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->schedules->exists( $id ) ) {
			return $this->not_found();
		}
		$affected = 0;
		foreach ( $this->widgets->list()['items'] as $widget ) {
			if ( (int) ( $widget['config']['schedule']['schedule_id'] ?? 0 ) === $id ) {
				$widget['config']['schedule']['schedule_id'] = 0;
				$widget['config']['schedule']['enabled']     = false;
				$this->widgets->update(
					$widget['id'],
					array(
						'title'   => $widget['title'],
						'enabled' => $widget['enabled'],
						'config'  => $widget['config'],
					)
				);
				++$affected;
			}
		}
		$this->schedules->delete( $id );
		return rest_ensure_response(
			array(
				'deleted'          => true,
				'id'               => $id,
				'widgets_affected' => $affected,
			)
		);
	}

	/**
	 * Duplicates.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate( \WP_REST_Request $request ) {
		$doc = $this->schedules->find( (int) $request['id'] );
		if ( ! $doc ) {
			return $this->not_found();
		}
		$id       = $this->schedules->create(
			array(
				/* translators: %s: schedule name */
				'title'   => sprintf( __( '%s (copy)', 'bigloop-call-button' ), $doc['title'] ),
				'enabled' => true,
				'config'  => $doc['config'],
			)
		);
		$response = rest_ensure_response( $this->present( (array) $this->schedules->find( (int) $id ), array() ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Dry-run summary for the editor.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$in     = (array) $request->get_json_params();
		$result = $this->sanitizer->sanitize(
			array(
				'title'  => 'preview',
				'config' => $in['config'] ?? array(),
			)
		);
		return rest_ensure_response( $this->summary( $result['data']['config'] ) + array( 'errors' => (object) $result['errors'] ) );
	}

	/**
	 * Open-now, next change and the coming week's hours in the schedule's own timezone.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return array<string,mixed>
	 */
	private function summary( array $config ): array {
		$resolved = ScheduleService::resolve( $config );
		$tz       = ScheduleEngine::zone( (string) $resolved['timezone'] );
		$now      = time();
		$windows  = ScheduleEngine::windows( $resolved, $now, $now + 8 * DAY_IN_SECONDS );
		$open     = false;
		$next     = null;
		foreach ( $windows as $w ) {
			if ( $now >= $w[0] && $now < $w[1] ) {
				$open = true;
				$next = $w[1];
				break;
			}
			if ( $w[0] > $now ) {
				$next = $w[0];
				break;
			}
		}

		$days  = array();
		$local = ( new \DateTimeImmutable( '@' . $now ) )->setTimezone( $tz )->setTime( 0, 0 );
		for ( $i = 0; $i < 7; $i++ ) {
			$day    = $local->modify( "+{$i} day" );
			$days[] = array(
				'date'      => $day->format( 'Y-m-d' ),
				'weekday'   => wp_date( 'l', $day->getTimestamp() + 43200, $tz ),
				'intervals' => ScheduleEngine::intervals_for( $resolved, $day ),
			);
		}

		return array(
			'timezone'    => (string) $resolved['timezone'],
			'open_now'    => $open,
			'next_change' => $next ? wp_date( 'D j M, H:i', $next, $tz ) : null,
			'local_now'   => wp_date( 'D j M, H:i', $now, $tz ),
			'week'        => $days,
		);
	}

	/**
	 * Response shape.
	 *
	 * @param array<string,mixed>             $doc     Document.
	 * @param array<int, array<string,mixed>> $used_by Widgets.
	 * @return array<string,mixed>
	 */
	private function present( array $doc, array $used_by ): array {
		return array(
			'id'       => $doc['id'],
			'title'    => $doc['title'],
			'config'   => $doc['config'],
			'summary'  => $this->summary( (array) $doc['config'] ),
			'used_by'  => $used_by,
			'modified' => $doc['modified'],
		);
	}

	/**
	 * Map of schedule id → widgets using it.
	 *
	 * @return array<int, array<int, array{id:int,title:string}>>
	 */
	private function usage(): array {
		$map = array();
		foreach ( $this->widgets->list()['items'] as $w ) {
			$sid = (int) ( $w['config']['schedule']['schedule_id'] ?? 0 );
			if ( $sid ) {
				$map[ $sid ][] = array(
					'id'    => $w['id'],
					'title' => $w['title'],
				);
			}
		}
		return $map;
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'blcb_not_found', __( 'Schedule not found.', 'bigloop-call-button' ), array( 'status' => 404 ) );
	}

	/**
	 * 400.
	 *
	 * @param array<string,string> $errors Errors.
	 * @return \WP_Error
	 */
	private function invalid( array $errors ): \WP_Error {
		return new \WP_Error(
			'blcb_invalid_schedule',
			__( 'Please fix the highlighted fields.', 'bigloop-call-button' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}
}
