<?php
/**
 * Versioned, forward-only database migrations.
 *
 * @package Bigloop\CallButton
 */

namespace Bigloop\CallButton\Database;

use Bigloop\CallButton\Database\Migrations\M0001AnalyticsTables;

defined( 'ABSPATH' ) || exit;

/**
 * Applies pending migrations in order and records the schema version per site.
 */
final class Migrator {

	public const VERSION_OPTION = 'blcb_db_version';
	public const ERROR_OPTION   = 'blcb_db_error';
	public const LOCK_OPTION    = 'blcb_db_migrating';
	public const LOCK_TTL       = 600;

	/**
	 * Database.
	 *
	 * @var \wpdb
	 */
	private $wpdb;

	/**
	 * Migrations (lazy).
	 *
	 * @var Migration[]|null
	 */
	private $migrations = null;

	/**
	 * Constructor.
	 *
	 * @param \wpdb            $wpdb       Database.
	 * @param Migration[]|null $migrations Optional explicit list (tests).
	 */
	public function __construct( $wpdb, ?array $migrations = null ) {
		$this->wpdb       = $wpdb;
		$this->migrations = $migrations;
	}

	/**
	 * All migrations sorted by version.
	 *
	 * @return Migration[]
	 */
	public function migrations(): array {
		if ( null === $this->migrations ) {
			$this->migrations = array(
				new M0001AnalyticsTables(),
			);
		}
		$list = $this->migrations;
		usort(
			$list,
			static function ( Migration $a, Migration $b ) {
				return $a->version() <=> $b->version();
			}
		);
		return $list;
	}

	/**
	 * Latest available schema version.
	 *
	 * @return int
	 */
	public function latest_version(): int {
		$list = $this->migrations();
		return $list ? end( $list )->version() : 0;
	}

	/**
	 * Current site schema version.
	 *
	 * @return int
	 */
	public function current_version(): int {
		return (int) get_option( self::VERSION_OPTION, 0 );
	}

	/**
	 * Whether migrations are pending.
	 *
	 * @return bool
	 */
	public function needs_migration(): bool {
		return $this->current_version() < $this->latest_version();
	}

	/**
	 * Migrates only when needed. Safe to call on every request.
	 *
	 * @return void
	 */
	public function maybe_migrate(): void {
		if ( $this->needs_migration() ) {
			$this->migrate();
		}
	}

	/**
	 * Runs all pending migrations under a lock.
	 *
	 * @return bool True when the schema is current afterwards.
	 */
	public function migrate(): bool {
		if ( ! $this->acquire_lock() ) {
			return false;
		}

		try {
			$current = $this->current_version();
			foreach ( $this->migrations() as $migration ) {
				if ( $migration->version() <= $current ) {
					continue;
				}
				$this->wpdb->last_error = '';
				$migration->up( $this->wpdb );
				if ( '' !== (string) $this->wpdb->last_error ) {
					update_option(
						self::ERROR_OPTION,
						array(
							'version' => $migration->version(),
							'error'   => (string) $this->wpdb->last_error,
							'time'    => time(),
						),
						false
					);
					return false;
				}
				$current = $migration->version();
				update_option( self::VERSION_OPTION, $current, true );
			}
			delete_option( self::ERROR_OPTION );
			return true;
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Last migration error, if any.
	 *
	 * @return array{version:int,error:string,time:int}|null
	 */
	public function last_error(): ?array {
		$error = get_option( self::ERROR_OPTION );
		return is_array( $error ) ? $error : null;
	}

	/**
	 * Acquires the migration lock (add_option is atomic on the unique option_name key).
	 *
	 * @return bool
	 */
	private function acquire_lock(): bool {
		if ( add_option( self::LOCK_OPTION, time(), '', false ) ) {
			return true;
		}
		$since = (int) get_option( self::LOCK_OPTION, 0 );
		if ( $since && ( time() - $since ) > self::LOCK_TTL ) {
			// Stale lock from a crashed request.
			update_option( self::LOCK_OPTION, time(), false );
			return true;
		}
		return false;
	}
}
