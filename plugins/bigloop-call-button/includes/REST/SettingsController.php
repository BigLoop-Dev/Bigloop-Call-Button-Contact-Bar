<?php
/**
 * Settings endpoint.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\REST;

use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * GET/PUT /settings.
 */
final class SettingsController extends Controller {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			RestServiceProvider::REST_NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	/**
	 * Returns settings and the type schema (without defaults leaking implementation detail).
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings(): \WP_REST_Response {
		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Applies a partial update.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$input  = $request->get_json_params();
		$result = $this->settings->update( is_array( $input ) ? ( $input['values'] ?? $input ) : null );

		if ( ! empty( $result['errors'] ) ) {
			return new \WP_Error(
				'blcb_invalid_settings',
				__( 'Some settings are invalid. Nothing was saved.', 'bigloop-call-button' ),
				array(
					'status' => 400,
					'errors' => $result['errors'],
				)
			);
		}

		return rest_ensure_response( $this->payload() );
	}

	/**
	 * Response body.
	 *
	 * @return array<string, mixed>
	 */
	private function payload(): array {
		$schema = array();
		foreach ( $this->settings->schema() as $section => $fields ) {
			foreach ( $fields as $key => $def ) {
				$schema[ $section ][ $key ] = array_intersect_key( $def, array_flip( array( 'type', 'default', 'min', 'max', 'choices', 'max_length' ) ) );
			}
		}
		return array(
			'values' => $this->settings->all(),
			'schema' => $schema,
		);
	}
}
