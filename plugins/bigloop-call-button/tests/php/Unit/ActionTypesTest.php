<?php
// phpcs:disable

use Bigloop\CallButton\Actions\ActionTypeRegistry;
use Bigloop\CallButton\Actions\FieldValidator;
use PHPUnit\Framework\TestCase;

final class ActionTypesTest extends TestCase {

	private function href( string $type, array $value ): string {
		$t = ActionTypeRegistry::with_defaults()->get( $type );
		$r = $t->validate( $value );
		$this->assertEmpty( $r['errors'], "{$type}: " . json_encode( $r['errors'] ) );
		return $t->href( $r['value'] );
	}

	public function test_links_for_every_builtin_type(): void {
		$this->assertSame( 'tel:+15551234567', $this->href( 'phone', array( 'number' => '+1 (555) 123-4567' ) ) );
		$this->assertSame( 'tel:0201234567,22', $this->href( 'phone', array( 'number' => '020 123 4567 ext. 22' ) ) );
		$this->assertSame( 'sms:+15551234567?&body=Hello%20there', $this->href( 'sms', array( 'number' => '+1 555 123 4567', 'body' => 'Hello there' ) ) );
		$this->assertSame( 'https://wa.me/447946095800', $this->href( 'whatsapp', array( 'number' => '0044 7946 095800' ) ) );
		$this->assertSame( 'https://wa.me/15551234567?text=Hi%20%26%20bye', $this->href( 'whatsapp', array( 'number' => '+1-555-123-4567', 'message' => 'Hi & bye' ) ) );
		$this->assertSame( 'mailto:a@b.co?subject=Hi&body=Line%201%0ALine%202', $this->href( 'email', array( 'address' => 'a@b.co', 'subject' => 'Hi', 'body' => "Line 1\nLine 2" ) ) );
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=Eiffel%20Tower', $this->href( 'map', array( 'query' => 'Eiffel Tower' ) ) );
		$this->assertSame( 'https://www.google.com/maps/search/?api=1&query=Eiffel%20Tower&query_place_id=ChIJLU7jZClu5kcR4PcOOO6p3I0', $this->href( 'map', array( 'query' => 'Eiffel Tower', 'place_id' => 'ChIJLU7jZClu5kcR4PcOOO6p3I0' ) ) );
		$this->assertSame( 'https://www.google.com/maps/dir/?api=1&destination=48.8584%2C2.2945', $this->href( 'directions', array( 'destination' => '48.8584,2.2945' ) ) );
		$this->assertSame( 'https://cal.example.com/book', $this->href( 'booking', array( 'url' => 'https://cal.example.com/book' ) ) );
		$this->assertSame( 'https://example.com/contact?x=1', $this->href( 'url', array( 'url' => 'https://example.com/contact?x=1' ) ) );
		$this->assertSame( 'facetime:+15551234567', $this->href( 'custom', array( 'url' => 'facetime:+15551234567' ) ) );
		$this->assertSame( 'https://t.me/bigloop_dev', $this->href( 'telegram', array( 'username' => '@bigloop_dev' ) ) );
		$this->assertSame( 'https://m.me/bigloop.dev', $this->href( 'messenger', array( 'username' => 'bigloop.dev' ) ) );
		$this->assertSame( 'viber://chat?number=%2B380441234567', $this->href( 'viber', array( 'number' => '+380 44 123 4567' ) ) );
		$this->assertSame( 'https://signal.me/#p/+15551234567', $this->href( 'signal', array( 'number' => '+1 555 123 4567' ) ) );
		$this->assertSame( 'https://line.me/R/ti/p/%40myshop', $this->href( 'line', array( 'line_id' => '@myshop' ) ) );
	}

	public function test_required_fields_and_bad_values_are_rejected(): void {
		$reg = ActionTypeRegistry::with_defaults();
		$this->assertArrayHasKey( 'number', $reg->get( 'phone' )->validate( array() )['errors'] );
		$this->assertArrayHasKey( 'number', $reg->get( 'phone' )->validate( array( 'number' => 'call me maybe' ) )['errors'] );
		$this->assertArrayHasKey( 'number', $reg->get( 'whatsapp' )->validate( array( 'number' => '0123' ) )['errors'] );
		$this->assertArrayHasKey( 'address', $reg->get( 'email' )->validate( array( 'address' => 'not-an-email' ) )['errors'] );
		$this->assertArrayHasKey( 'url', $reg->get( 'url' )->validate( array( 'url' => 'javascript:alert(1)' ) )['errors'] );
		$this->assertArrayHasKey( 'url', $reg->get( 'url' )->validate( array( 'url' => 'example.com' ) )['errors'] );
		$this->assertArrayHasKey( 'url', $reg->get( 'custom' )->validate( array( 'url' => 'data:text/html,hi' ) )['errors'] );
		$this->assertArrayHasKey( 'url', $reg->get( 'booking' )->validate( array( 'url' => 'https://exa mple.com' ) )['errors'] );
		$this->assertArrayHasKey( 'username', $reg->get( 'telegram' )->validate( array( 'username' => 'ab' ) )['errors'] );
		$this->assertArrayHasKey( 'place_id', $reg->get( 'map' )->validate( array( 'query' => 'x', 'place_id' => 'bad id!' ) )['errors'] );
	}

	public function test_unknown_value_keys_are_dropped(): void {
		$r = ActionTypeRegistry::with_defaults()->get( 'phone' )->validate( array( 'number' => '5551234', 'evil' => '<script>' ) );
		$this->assertSame( array( 'number' => '5551234' ), $r['value'] );
	}

	public function test_custom_protocol_list_is_respected(): void {
		$field = array( 'kind' => 'custom_url', 'required' => true );
		$this->assertTrue( FieldValidator::validate( $field, 'skype:echo123?call' )['ok'] );
		$this->assertFalse( FieldValidator::validate( $field, 'skype:echo123?call', array( 'https' ) )['ok'] );
	}

	public function test_default_colours_meet_contrast_with_white(): void {
		$lum = static function ( string $hex ): float {
			$c = array_map( static fn( $i ) => hexdec( substr( $hex, $i, 2 ) ) / 255, array( 1, 3, 5 ) );
			$c = array_map( static fn( $v ) => $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4, $c );
			return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
		};
		foreach ( ActionTypeRegistry::with_defaults()->all() as $id => $type ) {
			$colors = $type->colors();
			$ratio  = ( $lum( '#ffffff' ) + 0.05 ) / ( $lum( $colors['bg'] ) + 0.05 );
			$this->assertTrue( $ratio >= 4.5, "{$id} background {$colors['bg']} contrast {$ratio} < 4.5" );
		}
	}
}
