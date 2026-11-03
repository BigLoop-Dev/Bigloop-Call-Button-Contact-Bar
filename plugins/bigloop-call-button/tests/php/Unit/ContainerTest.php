<?php
// phpcs:disable

use Bigloop\CallButton\Core\Container;
use PHPUnit\Framework\TestCase;

final class ContainerTest extends TestCase {

	public function test_services_are_shared_and_lazy(): void {
		$c     = new Container();
		$built = 0;
		$c->set( 'svc', function () use ( &$built ) {
			$built++;
			return new \stdClass();
		} );
		$this->assertSame( 0, $built );
		$a = $c->get( 'svc' );
		$b = $c->get( 'svc' );
		$this->assertSame( $a, $b );
		$this->assertSame( 1, $built );
	}

	public function test_factory_receives_container(): void {
		$c = new Container();
		$c->set( 'a', function () { return 'A'; } );
		$c->set( 'b', function ( Container $c ) { return $c->get( 'a' ) . 'B'; } );
		$this->assertSame( 'AB', $c->get( 'b' ) );
	}

	public function test_unknown_service_throws(): void {
		$this->expectException( \RuntimeException::class );
		( new Container() )->get( 'missing' );
	}

	public function test_circular_dependency_throws(): void {
		$c = new Container();
		$c->set( 'x', function ( Container $c ) { return $c->get( 'y' ); } );
		$c->set( 'y', function ( Container $c ) { return $c->get( 'x' ); } );
		$this->expectException( \RuntimeException::class );
		$c->get( 'x' );
	}

	public function test_instance_and_has(): void {
		$c = new Container();
		$c->instance( 'cfg', array( 1 ) );
		$this->assertTrue( $c->has( 'cfg' ) );
		$this->assertFalse( $c->has( 'nope' ) );
		$this->assertSame( array( 1 ), $c->get( 'cfg' ) );
	}

	public function test_redefining_resets_instance(): void {
		$c = new Container();
		$c->set( 's', function () { return 1; } );
		$c->get( 's' );
		$c->set( 's', function () { return 2; } );
		$this->assertSame( 2, $c->get( 's' ) );
	}
}
