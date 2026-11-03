<?php
/**
 * Dependency-free scalar sanitisers shared by domain sanitisers (unit-testable without WordPress).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Each method returns a clean value, or null when the input is not acceptable.
 */
final class Sanitize {

	/**
	 * Plain single-line text: tags, control characters and surrounding whitespace removed.
	 *
	 * @param mixed $value Raw.
	 * @param int   $max   Max characters.
	 * @return string|null
	 */
	public static function text( $value, int $max = 200 ): ?string {
		if ( is_int( $value ) || is_float( $value ) ) {
			$value = (string) $value;
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = preg_replace( '/<[^>]*>/', '', $value ) ?? '';
		$value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value ) ?? '';
		$value = trim( preg_replace( '/\s{2,}/u', ' ', $value ) ?? '' );
		if ( mb_strlen( $value ) > $max ) {
			return null;
		}
		return $value;
	}

	/**
	 * Multi-line plain text (newlines kept).
	 *
	 * @param mixed $value Raw.
	 * @param int   $max   Max characters.
	 * @return string|null
	 */
	public static function textarea( $value, int $max = 1000 ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = preg_replace( '/<[^>]*>/', '', $value ) ?? '';
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = trim( preg_replace( '/[\x00-\x09\x0B-\x1F\x7F]+/u', '', $value ) ?? '' );
		if ( mb_strlen( $value ) > $max ) {
			return null;
		}
		return $value;
	}

	/**
	 * Boolean from common representations.
	 *
	 * @param mixed $value Raw.
	 * @return bool|null
	 */
	public static function bool( $value ): ?bool {
		if ( is_bool( $value ) ) {
			return $value;
		}
		if ( in_array( $value, array( 1, '1', 'true', 'on', 'yes' ), true ) ) {
			return true;
		}
		if ( in_array( $value, array( 0, '0', 'false', 'off', 'no', '' ), true ) ) {
			return false;
		}
		return null;
	}

	/**
	 * Integer within bounds.
	 *
	 * @param mixed $value Raw.
	 * @param int   $min   Minimum.
	 * @param int   $max   Maximum.
	 * @return int|null
	 */
	public static function int( $value, int $min, int $max ): ?int {
		if ( is_int( $value ) ) {
			$int = $value;
		} elseif ( is_string( $value ) && preg_match( '/^-?\d{1,12}$/', trim( $value ) ) ) {
			$int = (int) trim( $value );
		} elseif ( is_float( $value ) && floor( $value ) === $value && abs( $value ) < 1e12 ) {
			$int = (int) $value;
		} else {
			return null;
		}
		return ( $int < $min || $int > $max ) ? null : $int;
	}

	/**
	 * One of a set of strings.
	 *
	 * @param mixed    $value   Raw.
	 * @param string[] $choices Allowed.
	 * @return string|null
	 */
	public static function enum( $value, array $choices ): ?string {
		return ( is_string( $value ) && in_array( $value, $choices, true ) ) ? $value : null;
	}

	/**
	 * Hex colour (#rgb, #rrggbb, #rrggbbaa), lower-cased. Empty string means "inherit/default".
	 *
	 * @param mixed $value Raw.
	 * @return string|null
	 */
	public static function color( $value ): ?string {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $value ) ? $value : null;
	}

	/**
	 * Space-separated list of safe CSS class names.
	 *
	 * @param mixed $value Raw.
	 * @return string|null
	 */
	public static function css_classes( $value ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$classes = array_filter(
			array_map(
				static function ( $c ) {
					return preg_replace( '/[^A-Za-z0-9_-]/', '', $c );
				},
				preg_split( '/\s+/', $value ) ?: array()
			)
		);
		return implode( ' ', array_slice( array_unique( $classes ), 0, 10 ) );
	}

	/**
	 * Icon identifier (slug).
	 *
	 * @param mixed $value Raw.
	 * @return string|null
	 */
	public static function icon_id( $value ): ?string {
		if ( '' === $value ) {
			return '';
		}
		return ( is_string( $value ) && preg_match( '/^[a-z0-9-]{1,64}$/', $value ) ) ? $value : null;
	}

	/**
	 * Absolute URL with an allowed scheme. Rejects whitespace, control characters and javascript:/data: URLs.
	 *
	 * @param mixed    $value     Raw.
	 * @param string[] $protocols Allowed schemes (lower case, without colon).
	 * @return string|null
	 */
	public static function url( $value, array $protocols = array( 'http', 'https' ) ): ?string {
		if ( ! is_string( $value ) ) {
			return null;
		}
		$value = trim( $value );
		if ( '' === $value || strlen( $value ) > 2000 || preg_match( '/[\s\x00-\x1F\x7F<>"\'`]/', $value ) ) {
			return null;
		}
		if ( ! preg_match( '/^([a-z][a-z0-9+.\-]*):/i', $value, $m ) ) {
			return null;
		}
		$scheme = strtolower( $m[1] );
		if ( ! in_array( $scheme, $protocols, true ) ) {
			return null;
		}
		if ( in_array( $scheme, array( 'http', 'https' ), true ) ) {
			$host = parse_url( $value, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure helper, no WP dependency.
			if ( ! is_string( $host ) || '' === $host || ! preg_match( '/^[\p{L}\p{N}.\-\[\]:]+$/u', $host ) ) {
				return null;
			}
		}
		return $value;
	}
}
