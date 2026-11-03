<?php
// phpcs:disable

use Bigloop\CallButton\Database\Migration;
use Bigloop\CallButton\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class FakeWpdb {
	public $last_error = '';
	public $log        = array();
}

final class FakeMigration implements Migration {
	private $v;
	private $error;
	public function __construct( int $v, string $error = '' ) {
		$this->v     = $v;
		$this->error = $error;
	}
	public function version(): int {
		return $this->v;
	}
	public function up( $wpdb ): void {
		$wpdb->log[]      = $this->v;
		$wpdb->last_error = $this->error;
	}
}

final class MigratorTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['blcb_test_options'] = array(); // Isolation under real PHPUnit too.
	}

	public function test_runs_pending_in_version_order(): void {
		$db = new FakeWpdb();
		$m  = new Migrator( $db, array( new FakeMigration( 3 ), new FakeMigration( 1 ), new FakeMigration( 2 ) ) );
		$this->assertTrue( $m->needs_migration() );
		$this->assertTrue( $m->migrate() );
		$this->assertSame( array( 1, 2, 3 ), $db->log );
		$this->assertSame( 3, $m->current_version() );
		$this->assertFalse( $m->needs_migration() );
	}

	public function test_skips_applied_migrations(): void {
		update_option( Migrator::VERSION_OPTION, 2 );
		$db = new FakeWpdb();
		( new Migrator( $db, array( new FakeMigration( 1 ), new FakeMigration( 2 ), new FakeMigration( 3 ) ) ) )->migrate();
		$this->assertSame( array( 3 ), $db->log );
	}

	public function test_stops_and_records_error(): void {
		$db = new FakeWpdb();
		$m  = new Migrator( $db, array( new FakeMigration( 1 ), new FakeMigration( 2, 'boom' ), new FakeMigration( 3 ) ) );
		$this->assertFalse( $m->migrate() );
		$this->assertSame( array( 1, 2 ), $db->log );
		$this->assertSame( 1, $m->current_version(), 'Version must stay at last successful migration.' );
		$this->assertSame( 'boom', $m->last_error()['error'] );
		$this->assertFalse( get_option( Migrator::LOCK_OPTION ), 'Lock must be released after failure.' );
	}

	public function test_error_cleared_after_success(): void {
		update_option( Migrator::ERROR_OPTION, array( 'version' => 1, 'error' => 'x', 'time' => 1 ) );
		( new Migrator( new FakeWpdb(), array( new FakeMigration( 1 ) ) ) )->migrate();
		$this->assertFalse( get_option( Migrator::ERROR_OPTION ) );
	}

	public function test_respects_active_lock(): void {
		add_option( Migrator::LOCK_OPTION, time() );
		$db = new FakeWpdb();
		$this->assertFalse( ( new Migrator( $db, array( new FakeMigration( 1 ) ) ) )->migrate() );
		$this->assertSame( array(), $db->log );
	}

	public function test_takes_over_stale_lock(): void {
		add_option( Migrator::LOCK_OPTION, time() - Migrator::LOCK_TTL - 5 );
		$db = new FakeWpdb();
		$this->assertTrue( ( new Migrator( $db, array( new FakeMigration( 1 ) ) ) )->migrate() );
		$this->assertSame( array( 1 ), $db->log );
	}

	public function test_maybe_migrate_noop_when_current(): void {
		update_option( Migrator::VERSION_OPTION, 1 );
		$db = new FakeWpdb();
		( new Migrator( $db, array( new FakeMigration( 1 ) ) ) )->maybe_migrate();
		$this->assertSame( array(), $db->log );
	}
}
