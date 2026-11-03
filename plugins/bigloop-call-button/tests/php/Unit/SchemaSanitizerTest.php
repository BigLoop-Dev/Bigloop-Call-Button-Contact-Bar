<?php
// phpcs:disable

use Bigloop\CallButton\Support\SchemaSanitizer;
use PHPUnit\Framework\TestCase;

final class SchemaSanitizerTest extends TestCase {

	private function schema(): array {
		return array(
			'general' => array(
				'flag'  => array( 'type' => 'bool', 'default' => false ),
				'days'  => array( 'type' => 'int', 'default' => 90, 'min' => 7, 'max' => 730 ),
				'mode'  => array( 'type' => 'enum', 'default' => 'a', 'choices' => array( 'a', 'b' ) ),
				'label' => array( 'type' => 'string', 'default' => '', 'max_length' => 10 ),
			),
		);
	}

	public function test_defaults(): void {
		$this->assertSame( array( 'general' => array( 'flag' => false, 'days' => 90, 'mode' => 'a', 'label' => '' ) ), SchemaSanitizer::defaults( $this->schema() ) );
	}

	public function test_normalize_drops_unknown_and_invalid_stored_values(): void {
		$out = SchemaSanitizer::normalize( $this->schema(), array( 'general' => array( 'flag' => '1', 'days' => 'abc', 'zzz' => 1 ), 'other' => array() ) );
		$this->assertTrue( $out['general']['flag'] );
		$this->assertSame( 90, $out['general']['days'] );
		$this->assertArrayNotHasKey( 'zzz', $out['general'] );
		$this->assertArrayNotHasKey( 'other', $out );
	}

	public function test_normalize_handles_non_array_storage(): void {
		$this->assertSame( SchemaSanitizer::defaults( $this->schema() ), SchemaSanitizer::normalize( $this->schema(), 'garbage' ) );
	}

	public function test_apply_valid_partial_update(): void {
		$current = SchemaSanitizer::defaults( $this->schema() );
		$r       = SchemaSanitizer::apply( $this->schema(), $current, array( 'general' => array( 'days' => '30', 'mode' => 'b' ) ) );
		$this->assertEmpty( $r['errors'] );
		$this->assertSame( 30, $r['values']['general']['days'] );
		$this->assertSame( 'b', $r['values']['general']['mode'] );
		$this->assertFalse( $r['values']['general']['flag'] );
	}

	public function test_apply_reports_field_errors(): void {
		$current = SchemaSanitizer::defaults( $this->schema() );
		$r       = SchemaSanitizer::apply(
			$this->schema(),
			$current,
			array( 'general' => array( 'flag' => 'yes', 'days' => 3, 'mode' => 'c', 'label' => 'way too long text', 'nope' => 1 ), 'bogus' => array() )
		);
		foreach ( array( 'general.flag', 'general.days', 'general.mode', 'general.label', 'general.nope', 'bogus' ) as $key ) {
			$this->assertArrayHasKey( $key, $r['errors'] );
		}
		$this->assertSame( $current, $r['values'], 'Invalid fields must not change values.' );
	}

	public function test_int_rejects_floats_and_bools(): void {
		$def = array( 'type' => 'int' );
		$this->assertFalse( SchemaSanitizer::coerce( $def, '1.5' )['ok'] );
		$this->assertFalse( SchemaSanitizer::coerce( $def, true )['ok'] );
		$this->assertTrue( SchemaSanitizer::coerce( $def, ' 12 ' )['ok'] );
	}

	public function test_string_strips_tags_and_control_chars(): void {
		$r = SchemaSanitizer::coerce( array( 'type' => 'string', 'max_length' => 50 ), "<b>Hi</b>\x07 there<script>x</script>" );
		$this->assertTrue( $r['ok'] );
		$this->assertSame( 'Hi there', $r['value'] );
	}

	public function test_apply_rejects_non_object_payload(): void {
		$r = SchemaSanitizer::apply( $this->schema(), SchemaSanitizer::defaults( $this->schema() ), 'x' );
		$this->assertArrayHasKey( '_', $r['errors'] );
	}
}
