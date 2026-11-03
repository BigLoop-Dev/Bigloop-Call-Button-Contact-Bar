<?php
/**
 * Pure, dependency-free validation of nested settings against a typed schema.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Schema format:
 *   [ section => [ field => [ 'type' => bool|int|enum|string, 'default' => mixed, ...constraints ] ] ]
 *
 * Constraints: int → min, max. enum → choices. string → max_length, pattern (+ pattern_error).
 */
final class SchemaSanitizer {

	/**
	 * Default values for a schema.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $schema Schema.
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults( array $schema ): array {
		$out = array();
		foreach ( $schema as $section => $fields ) {
			foreach ( $fields as $key => $def ) {
				$out[ $section ][ $key ] = $def['default'] ?? null;
			}
		}
		return $out;
	}

	/**
	 * Merges stored values over defaults, coercing each to its type and dropping unknown keys.
	 * Used when reading, so corrupt stored data never reaches callers.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $schema Schema.
	 * @param mixed                                              $stored Stored value.
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize( array $schema, $stored ): array {
		$values = self::defaults( $schema );
		if ( ! is_array( $stored ) ) {
			return $values;
		}
		foreach ( $schema as $section => $fields ) {
			if ( ! isset( $stored[ $section ] ) || ! is_array( $stored[ $section ] ) ) {
				continue;
			}
			foreach ( $fields as $key => $def ) {
				if ( ! array_key_exists( $key, $stored[ $section ] ) ) {
					continue;
				}
				$result = self::coerce( $def, $stored[ $section ][ $key ] );
				if ( $result['ok'] ) {
					$values[ $section ][ $key ] = $result['value'];
				}
			}
		}
		return $values;
	}

	/**
	 * Applies a partial update to current values.
	 *
	 * @param array<string, array<string, array<string, mixed>>> $schema  Schema.
	 * @param array<string, array<string, mixed>>                $current Current (normalized) values.
	 * @param mixed                                              $input   Partial input.
	 * @return array{values: array<string, array<string, mixed>>, errors: array<string, string>}
	 */
	public static function apply( array $schema, array $current, $input ): array {
		$errors = array();
		$values = $current;

		if ( ! is_array( $input ) ) {
			return array(
				'values' => $values,
				'errors' => array( '_' => 'Settings payload must be an object.' ),
			);
		}

		foreach ( $input as $section => $fields ) {
			if ( ! isset( $schema[ $section ] ) ) {
				$errors[ (string) $section ] = 'Unknown settings section.';
				continue;
			}
			if ( ! is_array( $fields ) ) {
				$errors[ (string) $section ] = 'Section must be an object.';
				continue;
			}
			foreach ( $fields as $key => $raw ) {
				$path = $section . '.' . $key;
				if ( ! isset( $schema[ $section ][ $key ] ) ) {
					$errors[ $path ] = 'Unknown setting.';
					continue;
				}
				$result = self::coerce( $schema[ $section ][ $key ], $raw );
				if ( $result['ok'] ) {
					$values[ $section ][ $key ] = $result['value'];
				} else {
					$errors[ $path ] = $result['error'];
				}
			}
		}

		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Coerces a single value.
	 *
	 * @param array<string, mixed> $def Field definition.
	 * @param mixed                $raw Raw value.
	 * @return array{ok: bool, value?: mixed, error?: string}
	 */
	public static function coerce( array $def, $raw ): array {
		switch ( $def['type'] ?? '' ) {
			case 'bool':
				if ( is_bool( $raw ) ) {
					return array(
						'ok'    => true,
						'value' => $raw,
					);
				}
				if ( in_array( $raw, array( 1, 0, '1', '0', 'true', 'false' ), true ) ) {
					return array(
						'ok'    => true,
						'value' => in_array( $raw, array( 1, '1', 'true' ), true ),
					);
				}
				return array(
					'ok'    => false,
					'error' => 'Must be true or false.',
				);

			case 'int':
				if ( is_bool( $raw ) || ! is_numeric( $raw ) || trim( (string) $raw ) !== (string) (int) $raw ) {
					return array(
						'ok'    => false,
						'error' => 'Must be a whole number.',
					);
				}
				$int = (int) $raw;
				if ( isset( $def['min'] ) && $int < $def['min'] ) {
					return array(
						'ok'    => false,
						'error' => sprintf( 'Must be at least %d.', $def['min'] ),
					);
				}
				if ( isset( $def['max'] ) && $int > $def['max'] ) {
					return array(
						'ok'    => false,
						'error' => sprintf( 'Must be at most %d.', $def['max'] ),
					);
				}
				return array(
					'ok'    => true,
					'value' => $int,
				);

			case 'enum':
				$choices = (array) ( $def['choices'] ?? array() );
				if ( is_string( $raw ) && in_array( $raw, $choices, true ) ) {
					return array(
						'ok'    => true,
						'value' => $raw,
					);
				}
				return array(
					'ok'    => false,
					'error' => 'Must be one of: ' . implode( ', ', $choices ) . '.',
				);

			case 'string':
				if ( ! is_string( $raw ) ) {
					return array(
						'ok'    => false,
						'error' => 'Must be text.',
					);
				}
				// Strip tags and control characters; keep plain text only.
				$clean = trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', wp_strip_all_tags( $raw ) ) ?? '' );
				$max   = (int) ( $def['max_length'] ?? 200 );
				if ( mb_strlen( $clean ) > $max ) {
					return array(
						'ok'    => false,
						'error' => sprintf( 'Must be %d characters or fewer.', $max ),
					);
				}
				if ( isset( $def['pattern'] ) && '' !== $clean && ! preg_match( (string) $def['pattern'], $clean ) ) {
					return array(
						'ok'    => false,
						'error' => (string) ( $def['pattern_error'] ?? 'Invalid format.' ),
					);
				}
				return array(
					'ok'    => true,
					'value' => $clean,
				);
		}

		return array(
			'ok'    => false,
			'error' => 'Unsupported setting type.',
		);
	}
}
