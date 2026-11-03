<?php
// phpcs:disable

use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\Presets\BuiltinPresets;
use Bigloop\CallButton\Rendering\Renderer;
use PHPUnit\Framework\TestCase;

final class Phase4Test extends TestCase {

	private function sanitizer(): WidgetSanitizer {
		return new WidgetSanitizer( static fn( $id ) => true, static fn( $i ) => true );
	}

	public function test_squircle_path_is_closed_and_inside_box(): void {
		$path = Renderer::squircle_path( 56, 56 );
		$this->assertSame( 'M', $path[0] );
		$this->assertSame( 'Z', substr( $path, -1 ) );
		preg_match_all( '/(-?\d+(?:\.\d+)?) (-?\d+(?:\.\d+)?)/', $path, $m, PREG_SET_ORDER );
		$this->assertCount( 72, $m );
		foreach ( $m as $pt ) {
			$this->assertTrue( $pt[1] >= 0 && $pt[1] <= 56 && $pt[2] >= 0 && $pt[2] <= 56, "point {$pt[0]} outside box" );
		}
		// A superellipse is "fuller" than a circle: the 45° point lies outside the inscribed circle.
		$corner = $m[9]; // t = 45°.
		$dist   = sqrt( ( $corner[1] - 28 ) ** 2 + ( $corner[2] - 28 ) ** 2 );
		$this->assertTrue( $dist > 28, "45° point distance {$dist} should exceed the circle radius" );
	}

	public function test_upgrade_fills_new_keys_and_keeps_stored_values(): void {
		$old = array( 'schema' => 1, 'type' => 'menu', 'appearance' => array( 'size' => 64, 'pulse' => true ), 'actions' => array( array( 'id' => 3, 'label' => '' ) ) );
		$up  = WidgetSanitizer::upgrade( $old );
		$this->assertSame( WidgetSanitizer::SCHEMA, $up['schema'] );
		$this->assertSame( 64, $up['appearance']['size'] );
		$this->assertSame( 'pulse', $up['effects']['attention'] );
		$this->assertSame( 'system', $up['typography']['font'] );
		$this->assertSame( array( array( 'id' => 3, 'label' => '', 'when' => 'always' ) ), $up['actions'] );
		$this->assertFalse( $up['schedule']['enabled'] );
		$this->assertSame( array( 'match' => 'all', 'items' => array() ), $up['rules'] );
		$this->assertFalse( $up['position']['tablet_custom'] );
	}

	public function test_nullable_responsive_values(): void {
		$r = $this->sanitizer()->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'appearance' => array( 'size_mobile' => '', 'size_tablet' => 50, 'icon_size_mobile' => 999 ) ) ) );
		$this->assertSame( null, $r['data']['config']['appearance']['size_mobile'] );
		$this->assertSame( 50, $r['data']['config']['appearance']['size_tablet'] );
		$this->assertArrayHasKey( 'appearance.icon_size_mobile', $r['errors'] );
	}

	public function test_custom_font_rejects_css_injection(): void {
		$s = $this->sanitizer();
		$this->assertEmpty( $s->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'typography' => array( 'custom_font' => 'Open Sans' ) ) ) )['errors'] );
		$bad = $s->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'typography' => array( 'custom_font' => 'x;}body{display:none' ) ) ) );
		$this->assertArrayHasKey( 'typography.custom_font', $bad['errors'] );
	}

	public function test_pulse_back_compat(): void {
		$r = $this->sanitizer()->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'appearance' => array( 'pulse' => true ) ) ) );
		$this->assertSame( 'pulse', $r['data']['config']['effects']['attention'] );
		$r = $this->sanitizer()->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'effects' => array( 'attention' => 'shake' ) ) ) );
		$this->assertFalse( $r['data']['config']['appearance']['pulse'] );
	}

	public function test_partial_keeps_only_given_style_keys(): void {
		$r = $this->sanitizer()->sanitize_partial(
			array( 'appearance' => array( 'shape' => 'pill', 'size' => 5 ), 'position' => array( 'desktop' => array( 'anchor' => 'top-left' ) ), 'typography' => array( 'weight' => '700', 'nope' => 1 ) ),
			array( 'appearance', 'typography', 'effects', 'bar' )
		);
		$this->assertSame( array( 'appearance' => array( 'shape' => 'pill' ), 'typography' => array( 'weight' => '700' ) ), $r['partial'] );
		$this->assertArrayHasKey( 'appearance.size', $r['errors'] );
	}

	public function test_builtin_presets_are_valid_and_readable(): void {
		$lum = static function ( string $hex ): float {
			$c = array_map( static fn( $i ) => hexdec( substr( $hex, $i, 2 ) ) / 255, array( 1, 3, 5 ) );
			$c = array_map( static fn( $v ) => $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4, $c );
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		$ratio = static fn( $a, $b ) => ( max( $lum( $a ), $lum( $b ) ) + 0.05 ) / ( min( $lum( $a ), $lum( $b ) ) + 0.05 );

		$all = BuiltinPresets::all();
		$this->assertCount( 10, $all );
		foreach ( $all as $id => $preset ) {
			$r = $this->sanitizer()->sanitize_partial( $preset['config'], array( 'appearance', 'typography', 'effects', 'bar' ) );
			$this->assertEmpty( $r['errors'], "{$id}: " . json_encode( $r['errors'] ) );
			$a = $preset['config']['appearance'];
			if ( in_array( $a['style'] ?? 'solid', array( 'solid', 'gradient' ), true ) && isset( $a['fg'] ) ) {
				$this->assertTrue( $ratio( $a['bg'], $a['fg'] ) >= 4.5, "{$id} button text contrast" );
			}
			if ( isset( $a['label_bg'], $a['label_fg'] ) ) {
				$this->assertTrue( $ratio( $a['label_bg'], $a['label_fg'] ) >= 4.5, "{$id} label contrast" );
			}
			$b = $preset['config']['bar'] ?? array();
			if ( isset( $b['bg'], $b['fg'] ) ) {
				$this->assertTrue( $ratio( $b['bg'], $b['fg'] ) >= 4.5, "{$id} bar contrast" );
			}
		}
	}
}
