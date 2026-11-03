<?php
/**
 * Minimal PHPUnit-compatible TestCase used only when PHPUnit is not installed.
 * Implements the subset of assertions used by this suite.
 *
 * @package Bigloop\CallButton
 */

// phpcs:disable

namespace PHPUnit\Framework;

class AssertionFailedError extends \Exception {}

abstract class TestCase {
	public $assertions = 0;

	protected function setUp(): void {}
	protected function tearDown(): void {}

	public function runBare( string $method ): void {
		$this->setUp();
		try {
			$this->$method();
		} finally {
			$this->tearDown();
		}
	}

	private function fail_with( string $message ): void {
		throw new AssertionFailedError( $message );
	}

	public function assertTrue( $v, string $m = '' ): void {
		$this->assertions++;
		if ( true !== $v ) { $this->fail_with( $m ?: 'Failed asserting that value is true. Got ' . var_export( $v, true ) ); }
	}
	public function assertFalse( $v, string $m = '' ): void {
		$this->assertions++;
		if ( false !== $v ) { $this->fail_with( $m ?: 'Failed asserting that value is false. Got ' . var_export( $v, true ) ); }
	}
	public function assertSame( $expected, $actual, string $m = '' ): void {
		$this->assertions++;
		if ( $expected !== $actual ) { $this->fail_with( $m ?: 'Expected ' . var_export( $expected, true ) . ' but got ' . var_export( $actual, true ) ); }
	}
	public function assertArrayHasKey( $key, $array, string $m = '' ): void {
		$this->assertions++;
		if ( ! array_key_exists( $key, $array ) ) { $this->fail_with( $m ?: "Missing array key '{$key}'." ); }
	}
	public function assertArrayNotHasKey( $key, $array, string $m = '' ): void {
		$this->assertions++;
		if ( array_key_exists( $key, $array ) ) { $this->fail_with( $m ?: "Unexpected array key '{$key}'." ); }
	}
	public function assertEmpty( $v, string $m = '' ): void {
		$this->assertions++;
		if ( ! empty( $v ) ) { $this->fail_with( $m ?: 'Failed asserting value is empty: ' . var_export( $v, true ) ); }
	}
	public function assertCount( int $n, $v, string $m = '' ): void {
		$this->assertions++;
		if ( count( $v ) !== $n ) { $this->fail_with( $m ?: "Expected count {$n}, got " . count( $v ) ); }
	}

	private $expected_exception = null;
	public function expectException( string $class ): void {
		$this->expected_exception = $class;
	}
	public function expectedException(): ?string {
		return $this->expected_exception;
	}
}
