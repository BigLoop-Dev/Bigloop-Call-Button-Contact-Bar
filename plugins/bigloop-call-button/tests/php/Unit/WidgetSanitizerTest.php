<?php
// phpcs:disable

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Domain\ActionSanitizer;
use Bigloop\CallButton\Domain\WidgetSanitizer;
use Bigloop\CallButton\Support\Sanitize;
use PHPUnit\Framework\TestCase;

final class WidgetSanitizerTest extends TestCase {

	private function sanitizer(): WidgetSanitizer {
		return new WidgetSanitizer(
			static fn( int $id ) => in_array( $id, array( 1, 2, 3 ), true ),
			static fn( string $icon ) => in_array( $icon, array( 'phone', 'x', 'message-circle' ), true )
		);
	}

	public function test_valid_menu_with_defaults(): void {
		$r = $this->sanitizer()->sanitize( array( 'title' => 'Menu', 'enabled' => true, 'config' => array( 'type' => 'menu', 'actions' => array( array( 'id' => 2 ), array( 'id' => '1', 'label' => 'Call <b>now</b>' ), array( 'id' => 2 ) ) ) ) );
		$this->assertEmpty( $r['errors'] );
		$this->assertSame( array( array( 'id' => 2, 'label' => '', 'when' => 'always' ), array( 'id' => 1, 'label' => 'Call now', 'when' => 'always' ) ), $r['data']['config']['actions'], 'Duplicates removed, labels cleaned' );
		$this->assertSame( 56, $r['data']['config']['appearance']['size'] );
		$this->assertSame( 'message-circle', $r['data']['config']['content']['icon'] );
		$this->assertTrue( $r['data']['enabled'] );
	}

	public function test_bar_defaults_hide_desktop(): void {
		$d = WidgetSanitizer::defaults( 'bar' );
		$this->assertFalse( $d['visibility']['desktop'] );
		$this->assertTrue( $d['visibility']['mobile'] );
	}

	public function test_leaf_errors_have_paths_and_keep_defaults(): void {
		$r = $this->sanitizer()->sanitize(
			array(
				'title'  => 'X',
				'config' => array(
					'type'       => 'menu',
					'appearance' => array( 'size' => 9999, 'bg' => 'red', 'shape' => 'blob' ),
					'position'   => array( 'mobile' => array( 'anchor' => 'middle-center', 'offset_x' => -1 ) ),
					'content'    => array( 'icon' => 'unknown-icon' ),
					'advanced'   => array( 'css_class' => 'ok bad!class <x>' ),
				),
			)
		);
		foreach ( array( 'appearance.size', 'appearance.bg', 'appearance.shape', 'position.mobile.anchor', 'position.mobile.offset_x', 'content.icon' ) as $path ) {
			$this->assertArrayHasKey( $path, $r['errors'] );
		}
		$this->assertSame( 56, $r['data']['config']['appearance']['size'] );
		$this->assertSame( 'ok badclass x', $r['data']['config']['advanced']['css_class'] );
	}

	public function test_business_rules(): void {
		$s = $this->sanitizer();
		$this->assertArrayHasKey( 'actions', $s->sanitize( array( 'title' => 'B', 'config' => array( 'type' => 'button', 'actions' => array( 1, 2 ) ) ) )['errors'] );
		$this->assertArrayHasKey( 'actions', $s->sanitize( array( 'title' => 'E', 'enabled' => true, 'config' => array( 'type' => 'menu' ) ) )['errors'] );
		$this->assertArrayHasKey( 'actions.0', $s->sanitize( array( 'title' => 'M', 'config' => array( 'type' => 'menu', 'actions' => array( 99 ) ) ) )['errors'] );
		$this->assertArrayHasKey( 'title', $s->sanitize( array( 'title' => '  ', 'config' => array( 'type' => 'menu' ) ) )['errors'] );
		$this->assertArrayHasKey( 'type', $s->sanitize( array( 'title' => 'T', 'config' => array( 'type' => 'popup' ) ) )['errors'] );
	}

	public function test_ext_is_preserved_and_cleaned(): void {
		$r = $this->sanitizer()->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'ext' => array( 'pro' => array( 'n' => 5, 'flag' => true, 'html' => '<img src=x onerror=1>hi', 'bad key!' => 1 ) ) ) ) );
		$this->assertSame( array( 'pro' => array( 'n' => 5, 'flag' => true, 'html' => 'hi', 'badkey' => 1 ) ), $r['data']['config']['ext'] );
	}

	public function test_unknown_top_level_keys_dropped(): void {
		$r = $this->sanitizer()->sanitize( array( 'title' => 'X', 'config' => array( 'type' => 'menu', 'evil' => array( 'x' => 1 ) ) ) );
		$this->assertArrayNotHasKey( 'evil', $r['data']['config'] );
	}

	public function test_action_sanitizer(): void {
		$s = new ActionSanitizer( ActionTypeRegistry::with_defaults(), static fn( $i ) => 'phone' === $i );
		$r = $s->sanitize( array( 'type' => 'phone', 'label' => 'Call', 'icon' => 'phone', 'colors' => array( 'bg' => '#ABCDEF' ), 'value' => array( 'number' => '555 1234' ) ) );
		$this->assertEmpty( $r['errors'] );
		$this->assertSame( 'Call', $r['data']['title'], 'Title falls back to label' );
		$this->assertSame( '#abcdef', $r['data']['config']['colors']['bg'] );
		$this->assertSame( null, $r['data']['config']['new_tab'] );

		$bad = $s->sanitize( array( 'type' => 'phone', 'icon' => 'nope', 'colors' => array( 'fg' => 'blue' ), 'new_tab' => 'maybe', 'label' => str_repeat( 'x', 61 ), 'value' => array() ) );
		foreach ( array( 'icon', 'colors.fg', 'new_tab', 'label', 'value.number' ) as $k ) {
			$this->assertArrayHasKey( $k, $bad['errors'] );
		}
	}

	public function test_sanitize_helpers(): void {
		$this->assertSame( null, Sanitize::url( 'https://exa"mple.com' ) );
		$this->assertSame( 'https://münchen.de/x', Sanitize::url( 'https://münchen.de/x' ) );
		$this->assertSame( null, Sanitize::url( 'JAVASCRIPT:alert(1)', array( 'http', 'https' ) ) );
		$this->assertSame( 12, Sanitize::int( '12', 0, 20 ) );
		$this->assertSame( null, Sanitize::int( '12.5', 0, 20 ) );
		$this->assertSame( null, Sanitize::int( 30, 0, 20 ) );
		$this->assertSame( '', Sanitize::color( '' ) );
		$this->assertSame( null, Sanitize::color( 'rgb(0,0,0)' ) );
		$this->assertSame( 'a b', Sanitize::text( "a\x00\n b" ) );
	}
}
