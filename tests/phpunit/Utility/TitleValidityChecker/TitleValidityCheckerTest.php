<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\TitleValidityChecker;

use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\TitleValidityChecker
 */
class TitleValidityCheckerTest extends TestCase {

	/** @var TitleValidityChecker */
	private TitleValidityChecker $checker;

	protected function setUp(): void {
		$this->checker = new TitleValidityChecker();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleValidityChecker::hasValidEnding
	 */
	public function testHasValidEnding(): void {
		$this->assertTrue( $this->checker->hasValidEnding( 'Some_Page' ) );
		$this->assertTrue( $this->checker->hasValidEnding( 'SomePage' ) );
		$this->assertTrue( $this->checker->hasValidEnding( '' ) );

		$this->assertFalse( $this->checker->hasValidEnding( 'Some_Page_' ) );
		$this->assertFalse( $this->checker->hasValidEnding( '_' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleValidityChecker::hasDoubleColon
	 */
	public function testHasDoubleColon(): void {
		$this->assertFalse( $this->checker->hasDoubleColon( 'NS:Page' ) );
		$this->assertFalse( $this->checker->hasDoubleColon( 'NoColonAtAll' ) );

		$this->assertTrue( $this->checker->hasDoubleColon( 'NS:Sub:Page' ) );
		$this->assertTrue( $this->checker->hasDoubleColon( 'A:B:C' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleValidityChecker::hasValidNamespace
	 */
	public function testHasValidNamespace(): void {
		// Valid namespaces
		$this->assertTrue( $this->checker->hasValidNamespace( 'Documentation' ) );
		$this->assertTrue( $this->checker->hasValidNamespace( 'NS1' ) );
		$this->assertTrue( $this->checker->hasValidNamespace( '_Private' ) );
		$this->assertTrue( $this->checker->hasValidNamespace( 'My_Namespace' ) );

		// Starts with digit
		$this->assertFalse( $this->checker->hasValidNamespace( '123Space' ) );
		$this->assertFalse( $this->checker->hasValidNamespace( '1NS' ) );

		// Empty
		$this->assertFalse( $this->checker->hasValidNamespace( '' ) );

		// Contains special characters — previously passed with unanchored regex
		$this->assertFalse( $this->checker->hasValidNamespace( 'NS!@#' ) );
		$this->assertFalse( $this->checker->hasValidNamespace( 'NS-Name' ) );
		$this->assertFalse( $this->checker->hasValidNamespace( 'NS Name' ) );
		$this->assertFalse( $this->checker->hasValidNamespace( 'abc!@#def' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleValidityChecker::hasValidLength
	 */
	public function testHasValidLength(): void {
		$this->assertTrue( $this->checker->hasValidLength( '' ) );
		$this->assertTrue( $this->checker->hasValidLength( 'Short title' ) );
		$this->assertTrue( $this->checker->hasValidLength( str_repeat( 'a', 255 ) ) );

		$this->assertFalse( $this->checker->hasValidLength( str_repeat( 'a', 256 ) ) );
	}
}
