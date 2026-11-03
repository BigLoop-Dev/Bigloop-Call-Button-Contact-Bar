<?php
// phpcs:disable

use Bigloop\CallButton\Domain\ScheduleSanitizer;
use Bigloop\CallButton\Scheduler\ScheduleEngine;
use PHPUnit\Framework\TestCase;

final class ScheduleEngineTest extends TestCase {

	private function ts( string $local, string $tz ): int {
		return ( new DateTimeImmutable( $local, new DateTimeZone( $tz ) ) )->getTimestamp();
	}

	private function sched( array $over = array() ): array {
		return array_replace_recursive( ScheduleSanitizer::defaults(), array( 'timezone' => 'Europe/London' ), $over );
	}

	public function test_weekday_hours(): void {
		$s = $this->sched();
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 09:00', 'Europe/London' ) ), 'Mon 09:00 open' );
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 16:59', 'Europe/London' ) ) );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 17:00', 'Europe/London' ) ), 'end is exclusive' );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 08:59', 'Europe/London' ) ) );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-11-07 12:00', 'Europe/London' ) ), 'Saturday closed' );
	}

	public function test_timezone_is_respected(): void {
		$s = $this->sched( array( 'timezone' => 'America/New_York' ) );
		// 09:30 in New York = 14:30 in London (both on standard time in November).
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 14:30', 'Europe/London' ) ) );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-11-02 09:30', 'Europe/London' ) ) );
	}

	public function test_dst_spring_forward_keeps_local_hours(): void {
		// UK clocks go forward on Sunday 29 March 2026. Make Sunday + Monday open 09–17 local.
		$s    = $this->sched( array( 'weekly' => array( 'sun' => array( array( '09:00', '17:00' ) ) ) ) );
		$from = $this->ts( '2026-03-28 00:00', 'Europe/London' );
		$to   = $this->ts( '2026-03-31 00:00', 'Europe/London' );
		$w    = ScheduleEngine::windows( $s, $from, $to );
		$this->assertCount( 2, $w );
		$this->assertSame( $this->ts( '2026-03-29 09:00', 'Europe/London' ), $w[0][0] );
		$this->assertSame( 8 * 3600, $w[0][1] - $w[0][0], 'Still 8 hours on the DST day' );
		$this->assertSame( gmdate( 'H:i', $w[1][0] ), '08:00', 'Monday 09:00 BST = 08:00 UTC' );
	}

	public function test_overnight_interval(): void {
		$s = $this->sched( array( 'weekly' => array( 'fri' => array( array( '20:00', '02:00' ) ) ) ) );
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-06 23:30', 'Europe/London' ) ) );
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-07 01:30', 'Europe/London' ) ), 'after midnight into Saturday' );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-11-07 02:00', 'Europe/London' ) ) );
	}

	public function test_holidays_special_hours_and_date_range(): void {
		$s = $this->sched(
			array(
				'holidays'   => array( array( 'from' => '2026-12-24', 'to' => '2026-12-26', 'label' => 'Christmas' ) ),
				'special'    => array( array( 'date' => '2026-12-31', 'intervals' => array( array( '10:00', '13:00' ) ) ), array( 'date' => '2026-11-07', 'intervals' => array( array( '11:00', '15:00' ) ) ) ),
				'date_range' => array( 'from' => '', 'to' => '2027-06-30' ),
			)
		);
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-12-24 12:00', 'Europe/London' ) ) );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-12-25 12:00', 'Europe/London' ) ) );
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-12-31 12:00', 'Europe/London' ) ) );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2026-12-31 14:00', 'Europe/London' ) ), 'special hours replace weekly' );
		$this->assertTrue( ScheduleEngine::is_open( $s, $this->ts( '2026-11-07 12:00', 'Europe/London' ) ), 'special opening on a Saturday' );
		$this->assertFalse( ScheduleEngine::is_open( $s, $this->ts( '2027-07-05 10:00', 'Europe/London' ) ), 'after the date range' );
	}

	public function test_windows_are_merged_and_clipped(): void {
		$s = $this->sched( array( 'weekly' => array( 'mon' => array( array( '09:00', '12:00' ), array( '11:00', '14:00' ), array( '14:00', '15:00' ) ) ) ) );
		$from = $this->ts( '2026-11-02 10:00', 'Europe/London' );
		$w    = ScheduleEngine::windows( $s, $from, $from + 86400 );
		$this->assertSame( array( $from, $this->ts( '2026-11-02 15:00', 'Europe/London' ) ), $w[0] );
	}

	public function test_sanitizer(): void {
		$r = ( new ScheduleSanitizer() )->sanitize(
			array(
				'title'  => 'Office',
				'config' => array(
					'timezone' => 'Mars/Base',
					'weekly'   => array( 'mon' => array( array( '9am', '5pm' ), array( '12:00', '12:00' ), array( '18:00', '24:00' ) ) ),
					'holidays' => array( array( 'from' => '2026-02-30' ), array( 'from' => '2026-12-25', 'to' => '2026-12-20' ) ),
					'special'  => array( array( 'date' => '2026-12-31', 'intervals' => array() ), array( 'date' => '2026-12-31' ) ),
				),
			)
		);
		foreach ( array( 'timezone', 'weekly.mon.0', 'weekly.mon.1', 'holidays.0.from', 'holidays.1.to', 'special.1.date' ) as $k ) {
			$this->assertArrayHasKey( $k, $r['errors'] );
		}
		$this->assertSame( array( array( '18:00', '24:00' ) ), $r['data']['config']['weekly']['mon'] );
		$this->assertSame( array(), $r['data']['config']['special'][0]['intervals'], 'special day with no hours = closed' );
	}
}
