<?php
/**
 * Validates action destination fields by field kind.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Actions;

use Bigloop\CallButton\Support\Sanitize;

defined( 'ABSPATH' ) || exit;

/**
 * Field kinds:
 *  - phone       Local or international number. Keeps a leading "+", digits, and an optional extension after "," or ";".
 *  - intl_phone  International number for app links (WhatsApp, Signal, Viber): 7–15 digits incl. country code, stored as digits.
 *  - email       Email address.
 *  - url         http(s) URL.
 *  - custom_url  URL with any allowed protocol (http, https, tel, sms, mailto + app schemes).
 *  - text        Single line (max_length).
 *  - textarea    Multi-line (max_length).
 *  - handle      Username matching `pattern`; a leading "@" is removed.
 *  - place_id    Google Maps Place ID.
 */
final class FieldValidator {

	/** Default schemes accepted by custom_url fields (filterable in WordPress via bigloop_cb/custom_url_protocols). */
	public const CUSTOM_PROTOCOLS = array( 'http', 'https', 'tel', 'sms', 'mailto', 'skype', 'viber', 'tg', 'whatsapp', 'facetime', 'facetime-audio', 'maps', 'geo', 'zoommtg', 'msteams' );

	/**
	 * Validates one field.
	 *
	 * @param array<string,mixed> $field     Field definition.
	 * @param mixed               $raw       Raw value.
	 * @param string[]|null       $protocols Allowed custom_url protocols.
	 * @return array{ok: bool, value: string, error: string}
	 */
	public static function validate( array $field, $raw, ?array $protocols = null ): array {
		$required = ! empty( $field['required'] );
		$kind     = (string) ( $field['kind'] ?? 'text' );
		$max      = (int) ( $field['max_length'] ?? 200 );

		if ( null === $raw || ( is_string( $raw ) && '' === trim( $raw ) ) ) {
			return $required ? self::fail( __( 'This field is required.', 'bigloop-call-button' ) ) : self::ok( '' );
		}
		if ( ! is_scalar( $raw ) ) {
			return self::fail( __( 'Invalid value.', 'bigloop-call-button' ) );
		}
		$raw = trim( (string) $raw );

		switch ( $kind ) {
			case 'phone':
				return self::phone( $raw );

			case 'intl_phone':
				$digits = preg_replace( '/\D+/', '', preg_replace( '/^00/', '', $raw ) ?? '' ) ?? '';
				if ( strlen( $digits ) < 7 || strlen( $digits ) > 15 || '0' === $digits[0] ) {
					return self::fail( __( 'Enter the full international number including the country code, e.g. +1 555 123 4567.', 'bigloop-call-button' ) );
				}
				return self::ok( $digits );

			case 'email':
				if ( strlen( $raw ) > 254 || false === filter_var( $raw, FILTER_VALIDATE_EMAIL ) ) {
					return self::fail( __( 'Enter a valid email address.', 'bigloop-call-button' ) );
				}
				return self::ok( $raw );

			case 'url':
				$url = Sanitize::url( $raw );
				return null === $url ? self::fail( __( 'Enter a full web address starting with https:// or http://.', 'bigloop-call-button' ) ) : self::ok( $url );

			case 'custom_url':
				$url = Sanitize::url( $raw, $protocols ?? self::CUSTOM_PROTOCOLS );
				return null === $url ? self::fail( __( 'Enter a full link with an allowed protocol (for example https://, tel:, sms: or mailto:).', 'bigloop-call-button' ) ) : self::ok( $url );

			case 'textarea':
				$text = Sanitize::textarea( $raw, $max );
				return null === $text ? self::fail( sprintf( /* translators: %d: max characters */ __( 'Use %d characters or fewer.', 'bigloop-call-button' ), $max ) ) : self::ok( $text );

			case 'handle':
				$handle  = ltrim( $raw, '@' );
				$pattern = (string) ( $field['pattern'] ?? '/^[A-Za-z0-9_.]{1,64}$/' );
				return preg_match( $pattern, $handle ) ? self::ok( $handle ) : self::fail( (string) ( $field['pattern_error'] ?? __( 'Enter a valid username.', 'bigloop-call-button' ) ) );

			case 'place_id':
				return preg_match( '/^[A-Za-z0-9_-]{10,300}$/', $raw ) ? self::ok( $raw ) : self::fail( __( 'This does not look like a Google Place ID.', 'bigloop-call-button' ) );

			case 'text':
			default:
				$text = Sanitize::text( $raw, $max );
				return null === $text ? self::fail( sprintf( /* translators: %d: max characters */ __( 'Use %d characters or fewer.', 'bigloop-call-button' ), $max ) ) : self::ok( $text );
		}
	}

	/**
	 * Phone number for tel:/sms: links.
	 *
	 * @param string $raw Raw.
	 * @return array{ok: bool, value: string, error: string}
	 */
	private static function phone( string $raw ): array {
		$ext   = '';
		$parts = preg_split( '/\s*(?:[,;]|ext\.?|x)\s*/i', $raw, 2 );
		$main  = $parts[0] ?? '';
		if ( isset( $parts[1] ) ) {
			$ext = preg_replace( '/\D+/', '', $parts[1] ) ?? '';
		}
		if ( preg_match( '/[^0-9+\s().\-\/]/', $main ) ) {
			return self::fail( __( 'Phone numbers may contain digits, spaces, +, -, ( ) and an extension.', 'bigloop-call-button' ) );
		}
		$plus   = 0 === strpos( ltrim( $main ), '+' );
		$digits = preg_replace( '/\D+/', '', $main ) ?? '';
		if ( strlen( $digits ) < 3 || strlen( $digits ) > 17 ) {
			return self::fail( __( 'Enter a phone number with 3 to 17 digits.', 'bigloop-call-button' ) );
		}
		return self::ok( ( $plus ? '+' : '' ) . $digits . ( '' !== $ext ? ',' . $ext : '' ) );
	}

	/**
	 * Success helper.
	 *
	 * @param string $value Clean value.
	 * @return array{ok: bool, value: string, error: string}
	 */
	private static function ok( string $value ): array {
		return array(
			'ok'    => true,
			'value' => $value,
			'error' => '',
		);
	}

	/**
	 * Failure helper.
	 *
	 * @param string $error Message.
	 * @return array{ok: bool, value: string, error: string}
	 */
	private static function fail( string $error ): array {
		return array(
			'ok'    => false,
			'value' => '',
			'error' => $error,
		);
	}
}
