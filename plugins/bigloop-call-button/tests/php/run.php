<?php
/**
 * Fallback test runner for environments without PHPUnit (e.g. no Composer access).
 * With Composer available, prefer: composer install && composer test
 *
 * Usage: php tests/php/run.php
 *
 * @package Bigloop\CallButton
 */

// phpcs:disable

require __DIR__ . '/bootstrap.php';

$files = glob( __DIR__ . '/Unit/*Test.php' );
$total = 0;
$fail  = 0;
$asserts = 0;

foreach ( $files as $file ) {
	$before = get_declared_classes();
	require_once $file;
	$classes = array_diff( get_declared_classes(), $before );
	foreach ( $classes as $class ) {
		$ref = new ReflectionClass( $class );
		if ( $ref->isAbstract() || ! $ref->isSubclassOf( \PHPUnit\Framework\TestCase::class ) ) {
			continue;
		}
		foreach ( $ref->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( 0 !== strpos( $method->name, 'test' ) ) {
				continue;
			}
			$total++;
			$GLOBALS['blcb_test_options'] = array();
			$test = new $class();
			$label = $ref->getShortName() . '::' . $method->name;
			try {
				$test->runBare( $method->name );
				if ( $test->expectedException() ) {
					throw new \PHPUnit\Framework\AssertionFailedError( 'Expected exception ' . $test->expectedException() . ' was not thrown.' );
				}
				echo "  ✓ {$label}\n";
			} catch ( \PHPUnit\Framework\AssertionFailedError $e ) {
				$fail++;
				echo "  ✗ {$label}: {$e->getMessage()}\n";
			} catch ( \Throwable $e ) {
				if ( $test->expectedException() && $e instanceof \Throwable && is_a( $e, $test->expectedException() ) ) {
					echo "  ✓ {$label}\n";
				} else {
					$fail++;
					echo "  ✗ {$label}: " . get_class( $e ) . ': ' . $e->getMessage() . "\n";
				}
			}
			$asserts += $test->assertions;
		}
	}
}

echo "\n" . ( $total - $fail ) . "/{$total} tests passed ({$asserts} assertions)\n";
exit( $fail ? 1 : 0 );
