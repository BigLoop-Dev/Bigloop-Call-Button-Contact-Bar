<?php
/**
 * Widget endpoints.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Database\StatsReader;
use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\Rendering\Renderer;
use Bigloop\CallButton\Rendering\RuntimeCompiler;
use Bigloop\CallButton\Repositories\WidgetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   GET  /widgets               (search, status, type, orderby: order|title|date|modified|clicks, order, page, per_page)
 *   POST /widgets
 *   GET  /widgets/defaults?type=menu
 *   GET|PUT|DELETE /widgets/{id}
 *   POST /widgets/{id}/duplicate
 *   POST /widgets/{id}/status   { enabled }
 *   POST /widgets/reorder       { ids: [] }
 *   POST /widgets/preview       { title, config } → { html, css, inline, errors } (live preview)
 */
final class WidgetsController extends Controller {

	/**
	 * Widgets.
	 *
	 * @var WidgetRepository
	 */
	private $widgets;

	/**
	 * Sanitizer.
	 *
	 * @var WidgetSanitizer
	 */
	private $sanitizer;

	/**
	 * Stats.
	 *
	 * @var StatsReader
	 */
	private $stats;

	/**
	 * Compiler.
	 *
	 * @var RuntimeCompiler
	 */
	private $compiler;

	/**
	 * Renderer.
	 *
	 * @var Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param WidgetRepository $widgets   Widgets.
	 * @param WidgetSanitizer  $sanitizer Sanitizer.
	 * @param StatsReader      $stats     Stats.
	 * @param RuntimeCompiler  $compiler  Compiler.
	 * @param Renderer         $renderer  Renderer.
	 */
	public function __construct( WidgetRepository $widgets, WidgetSanitizer $sanitizer, StatsReader $stats, RuntimeCompiler $compiler, Renderer $renderer ) {
		$this->widgets   = $widgets;
		$this->sanitizer = $sanitizer;
		$this->stats     = $stats;
		$this->compiler  = $compiler;
		$this->renderer  = $renderer;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		$ns   = RestServiceProvider::REST_NAMESPACE;
		$perm = array( $this, 'can_manage' );
		$id   = array(
			'id' => array(
				'type'     => 'integer',
				'required' => true,
			),
		);

		register_rest_route(
			$ns,
			'/widgets',
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
			'/widgets/defaults',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'defaults' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/widgets/preview',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/widgets/reorder',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'reorder' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/widgets/(?P<id>\d+)',
			array(
				'args' => $id,
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
			'/widgets/(?P<id>\d+)/duplicate',
			array(
				'args'                => $id,
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/widgets/(?P<id>\d+)/status',
			array(
				'args'                => $id,
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * Lists widgets with click counts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$orderby  = (string) $request->get_param( 'orderby' );
		$order    = 'desc' === $request->get_param( 'order' ) ? 'desc' : 'asc';
		$per_page = max( 0, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$by_click = 'clicks' === $orderby;

		$result = $this->widgets->list(
			array(
				'search'   => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'status'   => in_array( $request->get_param( 'status' ), array( 'enabled', 'disabled' ), true ) ? $request->get_param( 'status' ) : 'any',
				'type'     => in_array( $request->get_param( 'type' ), WidgetSanitizer::TYPES, true ) ? $request->get_param( 'type' ) : '',
				'orderby'  => in_array( $orderby, array( 'title', 'date', 'modified', 'order' ), true ) ? $orderby : 'order',
				'order'    => $order,
				'page'     => $by_click ? 1 : $page,
				'per_page' => $by_click ? 0 : $per_page,
			)
		);

		$clicks = $this->stats->clicks_by_widget();
		$items  = array_map(
			function ( $doc ) use ( $clicks ) {
				return $this->present( $doc, $clicks[ $doc['id'] ] ?? 0 );
			},
			$result['items']
		);

		if ( $by_click ) {
			usort(
				$items,
				static function ( $a, $b ) use ( $order ) {
					return 'desc' === $order ? $b['clicks'] <=> $a['clicks'] : $a['clicks'] <=> $b['clicks'];
				}
			);
			$items = $per_page ? array_slice( $items, ( $page - 1 ) * $per_page, $per_page ) : $items;
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => $result['total'],
			)
		);
	}

	/**
	 * Renders an unsaved draft with the real front-end renderer (the builder's live preview).
	 * Invalid fields fall back to defaults so the preview always renders; their errors are returned.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function preview( \WP_REST_Request $request ): \WP_REST_Response {
		$in     = (array) $request->get_json_params();
		$result = $this->sanitizer->sanitize(
			array(
				'title'   => $in['title'] ?? 'Preview',
				'enabled' => false,
				'config'  => $in['config'] ?? array(),
			)
		);
		$widget = $this->compiler->compile_widget(
			array(
				'id'      => 0,
				'title'   => (string) $result['data']['title'],
				'enabled' => true,
				'config'  => $result['data']['config'],
			)
		);
		if ( ! $widget ) {
			return rest_ensure_response(
				array(
					'empty'  => true,
					'html'   => '',
					'css'    => '',
					'inline' => '',
					'errors' => (object) $result['errors'],
				)
			);
		}
		return rest_ensure_response(
			array(
				'empty'  => false,
				'html'   => $this->renderer->floating( $widget ),
				'css'    => $this->renderer->css( array( $widget ) ),
				'inline' => $this->renderer->inline( $widget, 'center' ),
				'errors' => (object) $result['errors'],
			)
		);
	}

	/**
	 * Default config for a type (used by the editor when creating).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function defaults( \WP_REST_Request $request ): \WP_REST_Response {
		$type = in_array( $request->get_param( 'type' ), WidgetSanitizer::TYPES, true ) ? (string) $request->get_param( 'type' ) : 'menu';
		return rest_ensure_response( WidgetSanitizer::defaults( $type ) );
	}

	/**
	 * One widget.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$doc = $this->widgets->find( (int) $request['id'] );
		if ( ! $doc ) {
			return $this->not_found();
		}
		return rest_ensure_response( $this->present( $doc, $this->stats->clicks_by_widget()[ $doc['id'] ] ?? 0 ) );
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
		$id = $this->widgets->create( $result['data'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->widgets->find( $id ), 0 ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Replaces.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->widgets->exists( $id ) ) {
			return $this->not_found();
		}
		$result = $this->sanitizer->sanitize( $request->get_json_params() );
		if ( $result['errors'] ) {
			return $this->invalid( $result['errors'] );
		}
		$saved = $this->widgets->update( $id, $result['data'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( $this->present( (array) $this->widgets->find( $id ), $this->stats->clicks_by_widget()[ $id ] ?? 0 ) );
	}

	/**
	 * Deletes permanently.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->widgets->exists( $id ) ) {
			return $this->not_found();
		}
		$this->widgets->delete( $id );
		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * Duplicates (copy starts disabled).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate( \WP_REST_Request $request ) {
		$id = $this->widgets->duplicate( (int) $request['id'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->widgets->find( $id ), 0 ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Enables/disables. Enabling a widget without actions is refused.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$doc = $this->widgets->find( $id );
		if ( ! $doc ) {
			return $this->not_found();
		}
		$enabled = (bool) rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		if ( $enabled && empty( $doc['config']['actions'] ) ) {
			return $this->invalid( array( 'actions' => __( 'Add at least one action before enabling this widget.', 'bigloop-call-button' ) ) );
		}
		$this->widgets->set_enabled( $id, $enabled );
		return rest_ensure_response( $this->present( (array) $this->widgets->find( $id ), $this->stats->clicks_by_widget()[ $id ] ?? 0 ) );
	}

	/**
	 * Saves manual order.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function reorder( \WP_REST_Request $request ) {
		$ids = $request->get_param( 'ids' );
		if ( ! is_array( $ids ) ) {
			return $this->invalid( array( 'ids' => __( 'Provide the widget ids in order.', 'bigloop-call-button' ) ) );
		}
		$this->widgets->reorder( array_map( 'absint', $ids ) );
		return rest_ensure_response( array( 'reordered' => true ) );
	}

	/**
	 * Response shape.
	 *
	 * @param array<string,mixed> $doc    Document.
	 * @param int                 $clicks Clicks.
	 * @return array<string,mixed>
	 */
	private function present( array $doc, int $clicks ): array {
		$c   = (array) $doc['config'];
		$vis = (array) ( $c['visibility'] ?? array() );
		return array(
			'id'            => $doc['id'],
			'title'         => $doc['title'],
			'enabled'       => $doc['enabled'],
			'type'          => (string) ( $c['type'] ?? $doc['type'] ),
			'config'        => $c,
			'actions_count' => count( (array) ( $c['actions'] ?? array() ) ),
			'devices'       => array_keys( array_filter( $vis ) ),
			'clicks'        => $clicks,
			'order'         => $doc['order'],
			'created'       => $doc['created'],
			'modified'      => $doc['modified'],
			'preview_url'   => add_query_arg( 'blcb_preview', $doc['id'], home_url( '/' ) ),
		);
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'blcb_not_found', __( 'Widget not found.', 'bigloop-call-button' ), array( 'status' => 404 ) );
	}

	/**
	 * 400 with field errors.
	 *
	 * @param array<string,string> $errors Errors.
	 * @return \WP_Error
	 */
	private function invalid( array $errors ): \WP_Error {
		return new \WP_Error(
			'blcb_invalid_widget',
			__( 'Please fix the highlighted fields.', 'bigloop-call-button' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}
}
