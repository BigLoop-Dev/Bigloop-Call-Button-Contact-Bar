<?php
/**
 * Design preset endpoints.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\Presets\BuiltinPresets;
use Bigloop\CallButton\Repositories\PresetRepository;
use Bigloop\CallButton\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Routes:
 *   GET    /presets                 Built-in + custom presets.
 *   POST   /presets                 { name, description, config } → custom preset.
 *   PUT    /presets/{id}            Update a custom preset.
 *   DELETE /presets/{id}            Delete a custom preset.
 *   POST   /presets/{key}/duplicate Copy a built-in ("builtin:modern-minimal") or custom ("12") preset.
 */
final class PresetsController extends Controller {

	public const SECTIONS = array( 'appearance', 'typography', 'effects', 'bar' );

	/**
	 * Custom presets.
	 *
	 * @var PresetRepository
	 */
	private $presets;

	/**
	 * Sanitizer.
	 *
	 * @var WidgetSanitizer
	 */
	private $sanitizer;

	/**
	 * Constructor.
	 *
	 * @param PresetRepository $presets   Presets.
	 * @param WidgetSanitizer  $sanitizer Sanitizer.
	 */
	public function __construct( PresetRepository $presets, WidgetSanitizer $sanitizer ) {
		$this->presets   = $presets;
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
			'/presets',
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
			'/presets/(?P<id>\d+)',
			array(
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
			'/presets/(?P<key>builtin:[a-z0-9-]+|\d+)/duplicate',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate' ),
				'permission_callback' => $perm,
			)
		);
	}

	/**
	 * All presets.
	 *
	 * @return \WP_REST_Response
	 */
	public function index(): \WP_REST_Response {
		$out = array();
		foreach ( BuiltinPresets::all() as $key => $preset ) {
			$out[] = array(
				'key'         => 'builtin:' . $key,
				'id'          => 0,
				'builtin'     => true,
				'name'        => $preset['name'],
				'description' => $preset['description'],
				'config'      => $preset['config'],
			);
		}
		foreach ( $this->presets->list( array( 'orderby' => 'title' ) )['items'] as $doc ) {
			$out[] = $this->present( $doc );
		}
		return rest_ensure_response( $out );
	}

	/**
	 * Creates a custom preset.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create( \WP_REST_Request $request ) {
		$data = $this->validate( $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id = $this->presets->create( $data );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$response = rest_ensure_response( $this->present( (array) $this->presets->find( $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Updates a custom preset.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->presets->exists( $id ) ) {
			return $this->not_found();
		}
		$data = $this->validate( $request->get_json_params() );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$this->presets->update( $id, $data );
		return rest_ensure_response( $this->present( (array) $this->presets->find( $id ) ) );
	}

	/**
	 * Deletes a custom preset.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function destroy( \WP_REST_Request $request ) {
		$id = (int) $request['id'];
		if ( ! $this->presets->exists( $id ) ) {
			return $this->not_found();
		}
		$this->presets->delete( $id );
		return rest_ensure_response(
			array(
				'deleted' => true,
				'id'      => $id,
			)
		);
	}

	/**
	 * Duplicates a built-in or custom preset into a new custom preset.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function duplicate( \WP_REST_Request $request ) {
		$key = (string) $request['key'];
		if ( 0 === strpos( $key, 'builtin:' ) ) {
			$all    = BuiltinPresets::all();
			$source = $all[ substr( $key, 8 ) ] ?? null;
			if ( ! $source ) {
				return $this->not_found();
			}
			$payload = array(
				'name'        => $source['name'],
				'description' => $source['description'],
				'config'      => $source['config'],
			);
		} else {
			$doc = $this->presets->find( (int) $key );
			if ( ! $doc ) {
				return $this->not_found();
			}
			$payload = array(
				'name'        => $doc['title'],
				'description' => (string) ( $doc['config']['description'] ?? '' ),
				'config'      => (array) ( $doc['config']['config'] ?? array() ),
			);
		}
		/* translators: %s: preset name */
		$payload['name'] = sprintf( __( '%s (copy)', 'bigloop-call-button' ), $payload['name'] );
		$data            = $this->validate( $payload );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$id       = $this->presets->create( $data );
		$response = rest_ensure_response( $this->present( (array) $this->presets->find( (int) $id ) ) );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * Validates a preset payload.
	 *
	 * @param mixed $input Raw.
	 * @return array{title:string,enabled:bool,config:array<string,mixed>}|\WP_Error
	 */
	private function validate( $input ) {
		$in     = is_array( $input ) ? $input : array();
		$errors = array();
		$name   = Sanitize::text( $in['name'] ?? '', 60 );
		if ( null === $name || '' === $name ) {
			$errors['name'] = __( 'Give the preset a name (up to 60 characters).', 'bigloop-call-button' );
		}
		$description = Sanitize::text( $in['description'] ?? '', 160 );
		if ( null === $description ) {
			$errors['description'] = __( 'Use 160 characters or fewer.', 'bigloop-call-button' );
		}
		$result = $this->sanitizer->sanitize_partial( $in['config'] ?? array(), self::SECTIONS );
		$errors = array_merge( $errors, $result['errors'] );
		if ( ! $result['partial'] ) {
			$errors['config'] = __( 'A preset needs at least one style setting.', 'bigloop-call-button' );
		}
		if ( $errors ) {
			return new \WP_Error(
				'blcb_invalid_preset',
				__( 'Please fix the highlighted fields.', 'bigloop-call-button' ),
				array(
					'status' => 400,
					'errors' => $errors,
				)
			);
		}
		return array(
			'title'   => (string) $name,
			'enabled' => true,
			'config'  => array(
				'type'        => 'preset',
				'description' => (string) $description,
				'config'      => $result['partial'],
			),
		);
	}

	/**
	 * Response shape for custom presets.
	 *
	 * @param array<string,mixed> $doc Document.
	 * @return array<string,mixed>
	 */
	private function present( array $doc ): array {
		return array(
			'key'         => (string) $doc['id'],
			'id'          => $doc['id'],
			'builtin'     => false,
			'name'        => $doc['title'],
			'description' => (string) ( $doc['config']['description'] ?? '' ),
			'config'      => (array) ( $doc['config']['config'] ?? array() ),
		);
	}

	/**
	 * 404.
	 *
	 * @return \WP_Error
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'blcb_not_found', __( 'Preset not found.', 'bigloop-call-button' ), array( 'status' => 404 ) );
	}
}
