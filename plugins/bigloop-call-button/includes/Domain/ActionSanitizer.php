<?php
/**
 * Validates contact action documents.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Domain;

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Action document (stored as JSON in post meta, title in post_title, enabled = post_status publish):
 *
 *   { schema, type, label, tooltip, aria_label, icon, colors: { bg, fg }, new_tab, value: { …type fields } }
 */
final class ActionSanitizer {

	public const SCHEMA = 1;

	/**
	 * Types.
	 *
	 * @var ActionTypeRegistry
	 */
	private $types;

	/**
	 * Icon existence check.
	 *
	 * @var callable(string): bool
	 */
	private $icon_exists;

	/**
	 * Allowed protocols for custom links.
	 *
	 * @var string[]|null
	 */
	private $protocols;

	/**
	 * Constructor.
	 *
	 * @param ActionTypeRegistry     $types       Types.
	 * @param callable(string): bool $icon_exists Icon check.
	 * @param string[]|null          $protocols   Custom-link protocols.
	 */
	public function __construct( ActionTypeRegistry $types, callable $icon_exists, ?array $protocols = null ) {
		$this->types       = $types;
		$this->icon_exists = $icon_exists;
		$this->protocols   = $protocols;
	}

	/**
	 * Validates a full action payload.
	 *
	 * @param mixed $input Raw payload: { title, enabled, type, label, tooltip, aria_label, icon, colors, new_tab, value }.
	 * @return array{data: array{title: string, enabled: bool, config: array<string,mixed>}, errors: array<string,string>}
	 */
	public function sanitize( $input ): array {
		$in     = is_array( $input ) ? $input : array();
		$errors = array();

		$type_id = is_string( $in['type'] ?? null ) ? $in['type'] : '';
		$type    = $this->types->get( $type_id );
		if ( ! $type ) {
			$errors['type'] = __( 'Choose an action type.', 'bigloop-call-button' );
		}

		$text = function ( string $key, int $max ) use ( $in, &$errors ): string {
			$value = Sanitize::text( $in[ $key ] ?? '', $max );
			if ( null === $value ) {
				/* translators: %d: max characters */
				$errors[ $key ] = sprintf( __( 'Use %d characters or fewer.', 'bigloop-call-button' ), $max );
				return '';
			}
			return $value;
		};

		$label      = $text( 'label', 60 );
		$tooltip    = $text( 'tooltip', 100 );
		$aria_label = $text( 'aria_label', 150 );
		$title      = $text( 'title', 100 );
		if ( '' === $title && ! isset( $errors['title'] ) ) {
			$title = '' !== $label ? $label : ( $type ? $type->label() : '' );
		}

		$icon = Sanitize::icon_id( $in['icon'] ?? '' );
		if ( null === $icon || ( '' !== $icon && ! ( $this->icon_exists )( $icon ) ) ) {
			$errors['icon'] = __( 'Choose an icon from the library.', 'bigloop-call-button' );
			$icon           = '';
		}

		$colors = array();
		foreach ( array( 'bg', 'fg' ) as $key ) {
			$c = Sanitize::color( $in['colors'][ $key ] ?? '' );
			if ( null === $c ) {
				$errors[ 'colors.' . $key ] = __( 'Use a hex colour such as #1a73e8.', 'bigloop-call-button' );
				$c                          = '';
			}
			$colors[ $key ] = $c;
		}

		$new_tab = null;
		if ( array_key_exists( 'new_tab', $in ) && null !== $in['new_tab'] ) {
			$new_tab = Sanitize::bool( $in['new_tab'] );
			if ( null === $new_tab ) {
				$errors['new_tab'] = __( 'Must be true or false.', 'bigloop-call-button' );
			}
		}

		$enabled = Sanitize::bool( $in['enabled'] ?? true );
		if ( null === $enabled ) {
			$errors['enabled'] = __( 'Must be true or false.', 'bigloop-call-button' );
			$enabled           = true;
		}

		$value = array();
		if ( $type ) {
			$result = $type->validate( $in['value'] ?? array(), $this->protocols );
			$value  = $result['value'];
			foreach ( $result['errors'] as $key => $message ) {
				$errors[ 'value.' . $key ] = $message;
			}
		}

		return array(
			'data'   => array(
				'title'   => $title,
				'enabled' => $enabled,
				'config'  => array(
					'schema'     => self::SCHEMA,
					'type'       => $type_id,
					'label'      => $label,
					'tooltip'    => $tooltip,
					'aria_label' => $aria_label,
					'icon'       => $icon,
					'colors'     => $colors,
					'new_tab'    => $new_tab,
					'value'      => $value,
				),
			),
			'errors' => $errors,
		);
	}
}
