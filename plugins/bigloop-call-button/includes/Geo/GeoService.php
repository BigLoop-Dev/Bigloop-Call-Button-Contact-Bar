<?php
/**
 * Visitor country detection without external services.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Geo;

use Bigloop\CallButton\Settings\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Reads a two-letter country code that the hosting stack already provides:
 *   - Cloudflare's CF-IPCountry header (when the site is proxied through Cloudflare with IP geolocation on), or
 *   - a server variable set by the host or web server (e.g. GEOIP_COUNTRY_CODE from mod_geoip / nginx GeoIP).
 *
 * No IP address is stored and no request leaves the server. Developers can plug in any other source with the
 * `bigloop_cb/geo_country` filter.
 */
final class GeoService {

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
	 * Whether country detection is turned on.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return (bool) $this->settings->get( 'geo.enabled' );
	}

	/**
	 * Visitor country (ISO 3166-1 alpha-2, upper case) or null when unknown.
	 *
	 * @param array<string,mixed>|null $server Server vars (tests).
	 * @return string|null
	 */
	public function country( ?array $server = null ): ?string {
		if ( ! $this->enabled() ) {
			return null;
		}
		$server = $server ?? $_SERVER; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below against a strict pattern.
		$source = (string) $this->settings->get( 'geo.source' );
		$code   = null;

		if ( 'cloudflare' === $source || 'auto' === $source ) {
			$code = $server['HTTP_CF_IPCOUNTRY'] ?? null;
		}
		if ( ( null === $code || '' === $code ) && ( 'header' === $source || 'auto' === $source ) ) {
			$var = strtoupper( (string) $this->settings->get( 'geo.header' ) );
			// Only geolocation-style variables may be read (never arbitrary environment values).
			if ( preg_match( '/^[A-Z0-9_]{1,60}$/', $var ) && preg_match( '/GEO|COUNTRY/', $var ) ) {
				$code = $server[ $var ] ?? null;
			}
		}

		$code = is_string( $code ) ? strtoupper( trim( $code ) ) : '';
		// "XX"/"T1" are Cloudflare's unknown / Tor markers.
		$code = preg_match( '/^[A-Z]{2}$/', $code ) && ! in_array( $code, array( 'XX', 'T1' ), true ) ? $code : null;

		/**
		 * Filters the detected visitor country.
		 *
		 * @since 0.4.0
		 *
		 * @param string|null $code   Two-letter code or null.
		 * @param array       $server Server variables.
		 */
		$code = apply_filters( 'bigloop_cb/geo_country', $code, $server );
		return is_string( $code ) && preg_match( '/^[A-Z]{2}$/', $code ) ? $code : null;
	}
}
