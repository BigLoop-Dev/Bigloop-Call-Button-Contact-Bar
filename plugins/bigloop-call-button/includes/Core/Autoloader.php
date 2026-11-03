<?php
/**
 * Minimal PSR-4 autoloader (no Composer required at runtime).
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Maps a namespace prefix to a base directory.
 */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 *
	 * @param string $prefix   Namespace prefix including trailing backslash.
	 * @param string $base_dir Base directory with trailing slash.
	 * @return void
	 */
	public static function register( string $prefix, string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class ) use ( $prefix, $base_dir ): void {
				$len = strlen( $prefix );
				if ( 0 !== strncmp( $prefix, $class, $len ) ) {
					return;
				}
				$relative = substr( $class, $len );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
