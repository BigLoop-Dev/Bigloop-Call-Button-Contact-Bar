<?php
/**
 * Curated SVG icon library (built by tools/build-icons.mjs from Lucide and Simple Icons).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Icons;

defined( 'ABSPATH' ) || exit;

/**
 * Reads assets/icons/icons.json once per request. SVG markup in the file is generated and sanitised at build time.
 */
final class IconRegistry {

	/**
	 * Path to the JSON library.
	 *
	 * @var string
	 */
	private $file;

	/**
	 * Loaded data.
	 *
	 * @var array{version?: string, categories?: array, icons?: array<string, array<string,string>>}|null
	 */
	private $data = null;

	/**
	 * Constructor.
	 *
	 * @param string $file JSON path.
	 */
	public function __construct( string $file ) {
		$this->file = $file;
	}

	/**
	 * Loads the library.
	 *
	 * @return array<string,mixed>
	 */
	private function data(): array {
		if ( null === $this->data ) {
			$json       = is_readable( $this->file ) ? file_get_contents( $this->file ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
			$decoded    = is_string( $json ) ? json_decode( $json, true ) : null;
			$this->data = is_array( $decoded ) ? $decoded : array( 'icons' => array() );
		}
		return $this->data;
	}

	/**
	 * Whether an icon exists.
	 *
	 * @param string $id Icon id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->data()['icons'][ $id ] );
	}

	/**
	 * SVG markup for an icon (empty string when unknown).
	 *
	 * @param string $id Icon id.
	 * @return string
	 */
	public function svg( string $id ): string {
		return (string) ( $this->data()['icons'][ $id ]['svg'] ?? '' );
	}

	/**
	 * Library version string.
	 *
	 * @return string
	 */
	public function version(): string {
		return (string) ( $this->data()['version'] ?? '' );
	}
}
