<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\HookHandler;

use HalloWelt\MigrateConfluence\Utility\HookHandler;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\HookHandler
 */
class HookHandlerTest extends TestCase {

	protected function setUp(): void {
		$this->resetHandlers();
	}

	protected function tearDown(): void {
		$this->resetHandlers();
	}

	/**
	 * Reset the static handler state via reflection so tests are independent.
	 */
	private function resetHandlers(): void {
		$ref = new ReflectionClass( HookHandler::class );
		$prop = $ref->getProperty( 'handlers' );
		$prop->setValue( null, [ 'filters' => [], 'actions' => [] ] );
	}

	public function testSetUpRegistersFilterCallback(): void {
		$called = false;
		HookHandler::setUp( [
			'filters' => [
				'my-filter' => static function ( $value ) use ( &$called ) {
					$called = true;
					return $value;
				},
			],
		] );
		HookHandler::filter( 'my-filter', 'original' );
		$this->assertTrue( $called, 'Filter callback should have been invoked' );
	}

	public function testSetUpRegistersActionCallback(): void {
		$called = false;
		HookHandler::setUp( [
			'actions' => [
				'my-action' => static function () use ( &$called ) {
					$called = true;
				},
			],
		] );
		HookHandler::run( 'my-action' );
		$this->assertTrue( $called, 'Action callback should have been invoked' );
	}

	public function testSetUpThrowsOnInvalidType(): void {
		$this->expectException( \InvalidArgumentException::class );
		HookHandler::setUp( [ 'invalid-type' => [] ] );
	}

	public function testSetUpThrowsOnNonCallableCallback(): void {
		$this->expectException( \InvalidArgumentException::class );
		HookHandler::setUp( [ 'filters' => [ 'my-filter' => 'not-a-callable' ] ] );
	}

	public function testFilterReturnsTransformedValue(): void {
		HookHandler::setUp( [
			'filters' => [
				'uppercase' => static fn ( string $v ) => strtoupper( $v ),
			],
		] );
		$result = HookHandler::filter( 'uppercase', 'hello' );
		$this->assertSame( 'HELLO', $result );
	}

	public function testFilterPassesAdditionalArgs(): void {
		HookHandler::setUp( [
			'filters' => [
				'append' => static fn ( string $v, string $suffix ) => $v . $suffix,
			],
		] );
		$result = HookHandler::filter( 'append', 'foo', '-bar' );
		$this->assertSame( 'foo-bar', $result );
	}

	public function testFilterReturnsValueUnchangedWhenNoHandlerRegistered(): void {
		$result = HookHandler::filter( 'no-handler', 'original' );
		$this->assertSame( 'original', $result );
	}

	public function testRunPassesArgsToActionCallback(): void {
		$received = null;
		HookHandler::setUp( [
			'actions' => [
				'ev' => static function ( $arg ) use ( &$received ) {
					$received = $arg;
				},
			],
		] );
		HookHandler::run( 'ev', 'payload' );
		$this->assertSame( 'payload', $received );
	}

	public function testRunDoesNothingWhenNoHandlerRegistered(): void {
		// Should not throw — just silently ignore
		HookHandler::run( 'unregistered-action' );
		$this->addToAssertionCount( 1 );
	}
}
