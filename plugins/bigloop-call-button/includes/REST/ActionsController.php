<?php
/**
 * Action library + action types endpoints.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Domain\ActionSanitizer;
use Bigloop\CallButton\Rendering\RuntimeCompiler;
use Bigloop\CallButton\Repositories\ActionRepository;
use Bigloop\CallButton\Repositories\WidgetRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   GET  /action-types
 *   GET  /actions               (search, status, type)
 *   POST /actions
 *   GET|PUT|DELETE /actions/{id}
 *   POST /actions/{id}/duplicate
 *   POST /actions/{id}/status   { enabled }
 *   POST /actions/validate      (dry run: returns errors + resulting link without saving)
 */
final class ActionsController extends Controller {

	/**
	 * Actions.
	 *
	 * @var ActionRepository
	 */
	private $actions;

	/**
	 * Widgets.
	 *
	 * @var WidgetRepository
	 */
	private $widgets;

	/**
	 * Sanitizer.
	 *
	 * @var ActionSanitizer
	 */
	private $sanitizer;

	/**
	 * Types.
	 *
	 * @var ActionTypeRegistry
	 */
	private $types;

	/**
	 * Compiler.
	 *
	 * @var RuntimeCompiler
	 */
	private $compiler;

	/**
	 * Constructor.
	 *
	 * @param ActionRepository   $actions   Actions.
	 * @param WidgetRepository   $widgets   Widgets.
	 * @param ActionSanitizer    $sanitizer Sanitizer.
	 * @param ActionTypeRegistry $types     Types.
	 * @param RuntimeCompiler    $compiler  Compiler.
	 */
	public function __construct( ActionRepository $actions, WidgetRepository $widgets, ActionSanitizer $sanitizer, ActionTypeRegistry $types, RuntimeCompiler $compiler ) {
		$this->actions   = $actions;
		$this->widgets   = $widgets;
		$this->sanitizer = $sanitizer;
		$this->types     = $types;
		$this->compiler  = $compiler;
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
			'/action-types',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'types' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/actions',
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
			'/actions/validate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/actions/(?P<id>\d+)',
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
			'/actions/(?P<id>\d+)/duplicate',
			array(
				'args'                => $id,
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $perm,
			)
		);
		register_rest_route(
			$ns,
			'/actions/(?P<id>\d+)/status',
			array(
				'args'                => $id,
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * Action types for the UI.
	 *
	 * @return \WP_REST_Response
	 */
	public function types(): \WP_REST_Response {
		return rest_ensure_response( array_values( array_map( static fn( $t ) => $t->to_array(), $this->types->all() ) ) );
	}

	/**
	 * Lists actions.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function index( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->actions->list(
			array(
				'search'  => sanitize_text_field( (string) $request->get_param( 'search' ) ),
				'status'  => in_array( $request->get_param( 'status' ), array( 'enabled', 'disabled' ), true ) ? $request->get_param( 'status' ) : 'any',
				'type'    => sanitize_key( (string) $request->get_param( 'type' ) ),
				'orderby' => in_array( $request->get_param( 'orderby' ), array( 'title', 'date', 'modified' ), true ) ? $request->get_param( 'orderby' ) : 'title',
				'order'   => 'desc' === $request->get_param( 'order' ) ? 'desc' : 'asc',
			)
		);
		$usage  = $this->usage_map();
		$items  = array_map(
			function ( $doc ) use ( $usage ) {
				return $this->present( $doc, $usage[ $doc['id'] ] ?? array() );
			},
			$result['items']
		);
		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => $result['total'],
			)
		);
	}

	/**
	 * One action.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function show( \WP_REST_Request $request ) {
		$doc = $this->actions->find( (int) $request['id'] );
		if ( ! $doc ) {
			return $this->not_found();
		}
		return rest_ensure_response( $this->present( $doc, $this->widgets->using_action( $doc['id'] ) ) );
	}

	/**
	 * Dry-run validation (live link preview in the editor).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function validate( \WP_REST_Request $request ): \WP_REST_Response {
		$result = $this->sanitizer->sanitize( $request->get_json_params() );
		$href   = '';
		$type   = $this->types->get( (string) $result['data']['config']['type'] );
		if ( $type && ! array_filter( array_keys( $result['errors'] ), static fn( $k ) => 0 === strpos( $k, 'value.' ) ) ) {
			$href = $type->href( $result['data']['config']['value'] );
		}
		return rest_ensure_response(
			array(
				'errors' => (object) $result['errors'],
				'href'   => $href,
			)
		);
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
		$id = $this->actions->create( $result['data'] );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->actions->find( $id ), array() ) );
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
		if ( ! $this->actions->exists( $id ) ) {
			return $this->not_found();
		}
		$result = $this->sanitizer->sanitize( $request->get_json_params() );
		if ( $result['errors'] ) {
			return $this->invalid( $result['errors'] );
		}
		$saved = $this->actions->update( $id, $result['data'] );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return rest_ensure_response( $this->present( (array) $this->actions->find( $id ), $this->widgets->using_action( $id ) ) );
	}

	/**
	 * Deletes and removes references from widgets.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->actions->exists( $id ) ) {
			return $this->not_found();
		}
		$affected = $this->widgets->remove_action_references( $id );
		$this->actions->delete( $id );
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
		$doc = $this->actions->find( (int) $request['id'] );
		if ( ! $doc ) {
			return $this->not_found();
		}
		$id = $this->actions->create(
			array(
				/* translators: %s: action name */
				'title'   => sprintf( __( '%s (copy)', 'bigloop-call-button' ), $doc['title'] ),
				'enabled' => $doc['enabled'],
				'config'  => $doc['config'],
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->actions->find( $id ), array() ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Enables/disables.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function status( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->actions->exists( $id ) ) {
			return $this->not_found();
		}
		$this->actions->set_enabled( $id, (bool) rest_sanitize_boolean( $request->get_param( 'enabled' ) ) );
		return rest_ensure_response( $this->present( (array) $this->actions->find( $id ), $this->widgets->using_action( $id ) ) );
	}

	/**
	 * Response shape.
	 *
	 * @param array<string,mixed>             $doc     Document.
	 * @param array<int, array<string,mixed>> $used_by Widgets using it.
	 * @return array<string,mixed>
	 */
	private function present( array $doc, array $used_by ): array {
		$c    = (array) $doc['config'];
		$type = $this->types->get( (string) ( $c['type'] ?? '' ) );
		$href = '';
		if ( $type ) {
			$check = $type->validate( $c['value'] ?? array(), $this->compiler->protocols() );
			$href  = $check['errors'] ? '' : $type->href( $check['value'] );
		}
		return array(
			'id'       => $doc['id'],
			'title'    => $doc['title'],
			'enabled'  => $doc['enabled'],
			'type'     => (string) ( $c['type'] ?? '' ),
			'config'   => $c,
			'href'     => $href,
			'used_by'  => $used_by,
			'created'  => $doc['created'],
			'modified' => $doc['modified'],
		);
	}

	/**
	 * Map of action id → widgets using it (one pass over all widgets).
	 *
	 * @return array<int, array<int, array{id:int,title:string}>>
	 */
	private function usage_map(): array {
		$map = array();
		foreach ( $this->widgets->list()['items'] as $widget ) {
			foreach ( (array) ( $widget['config']['actions'] ?? array() ) as $ref ) {
				$map[ (int) ( $ref['id'] ?? 0 ) ][ $widget['id'] ] = array(
					'id'    => $widget['id'],
					'title' => $widget['title'],
				);
			}
		}
		return array_map( 'array_values', $map );
	}

	/**
	 * 404 error.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'blcb_not_found', __( 'Action not found.', 'bigloop-call-button' ), array( 'status' => 404 ) );
	}

	/**
	 * 400 error with field errors.
	 *
	 * @param array<string,string> $errors Errors.
	 * @return \WP_Error
	 */
	private function invalid( array $errors ): \WP_Error {
		return new \WP_Error(
			'blcb_invalid_action',
			__( 'Please fix the highlighted fields.', 'bigloop-call-button' ),
			array(
				'status' => 400,
				'errors' => $errors,
			)
		);
	}
}
