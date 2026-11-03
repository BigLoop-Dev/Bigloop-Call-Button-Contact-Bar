<?php
/**
 * Plugin-wide settings.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Settings;

use Bigloop\CallButton\Support\SchemaSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Typed settings stored in a single option. Sections are added as features ship;
 * only settings whose behaviour is implemented are exposed.
 */
final class Settings {

	public const OPTION = 'blcb_settings';

	/**
	 * Cached normalized values.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private $values = null;

	/**
	 * Cached schema.
	 *
	 * @var array<string, array<string, array<string, mixed>>>|null
	 */
	private $schema = null;

	/**
	 * Settings schema.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function schema(): array {
		if ( null === $this->schema ) {
			$schema = array(
				'general'   => array(
					'delete_data_on_uninstall' => array(
						'type'    => 'bool',
						'default' => false,
					),
				),
				'access'    => array(
					'allow_editors' => array(
						'type'    => 'bool',
						'default' => false,
					),
				),
				'analytics' => array(
					'enabled'        => array(
						'type'    => 'bool',
						'default' => true,
					),
					'exclude_admins' => array(
						'type'    => 'bool',
						'default' => true,
					),
					'retention_days' => array(
						'type'    => 'int',
						'default' => 90,
						'min'     => 7,
						'max'     => 3650,
					),
					'ga_events'      => array(
						'type'    => 'bool',
						'default' => true,
					),
					'ga_event_name'  => array(
						'type'          => 'string',
						'default'       => 'bigloop_contact_click',
						'max_length'    => 40,
						'pattern'       => '/^[A-Za-z][A-Za-z0-9_]*$/',
						'pattern_error' => __( 'Use letters, numbers and underscores, starting with a letter.', 'bigloop-call-button' ),
					),
				),
				'geo'       => array(
					'enabled' => array(
						'type'    => 'bool',
						'default' => false,
					),
					'source'  => array(
						'type'    => 'enum',
						'default' => 'auto',
						'choices' => array( 'auto', 'cloudflare', 'header' ),
					),
					'header'  => array(
						'type'          => 'string',
						'default'       => 'GEOIP_COUNTRY_CODE',
						'max_length'    => 60,
						'pattern'       => '/^[A-Z0-9_]*(GEO|COUNTRY)[A-Z0-9_]*$/i',
						'pattern_error' => __( 'Use a server variable name containing GEO or COUNTRY, e.g. GEOIP_COUNTRY_CODE.', 'bigloop-call-button' ),
					),
				),
			);

			/**
			 * Filters the settings schema. Extensions add their own sections here.
			 *
			 * @since 0.1.0
			 *
			 * @param array $schema Schema (section => field => definition).
			 */
			$this->schema = (array) apply_filters( 'bigloop_cb/settings_schema', $schema );
		}
		return $this->schema;
	}

	/**
	 * All settings, normalized.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		if ( null === $this->values ) {
			$this->values = SchemaSanitizer::normalize( $this->schema(), get_option( self::OPTION, array() ) );
		}
		return $this->values;
	}

	/**
	 * Single setting by "section.key" path.
	 *
	 * @param string $path    Dotted path.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( string $path, $default = null ) {
		list( $section, $key ) = array_pad( explode( '.', $path, 2 ), 2, '' );
		$all                   = $this->all();
		return $all[ $section ][ $key ] ?? $default;
	}

	/**
	 * Applies a partial update. Nothing is saved when any field is invalid.
	 *
	 * @param mixed $input Partial settings.
	 * @return array{values: array<string, array<string, mixed>>, errors: array<string, string>}
	 */
	public function update( $input ): array {
		$result = SchemaSanitizer::apply( $this->schema(), $this->all(), $input );
		if ( empty( $result['errors'] ) ) {
			update_option( self::OPTION, $result['values'], true );
			$this->values = $result['values'];

			/**
			 * Fires after settings are saved.
			 *
			 * @since 0.1.0
			 *
			 * @param array $values Saved settings.
			 */
			do_action( 'bigloop_cb/settings_updated', $result['values'] );
		}
		return $result;
	}
}
