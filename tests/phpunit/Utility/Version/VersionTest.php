<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility;

use HalloWelt\MigrateConfluence\Utility\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\Version::getVersion
	 */
	public function testGetVersionReturnsNonemptyString(): void {
		$version = Version::getVersion();
		$this->assertIsString( $version );
		$this->assertNotEmpty( $version );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\Version::getVersion
	 */
	public function testGetVersionMatchesVersionFile(): void {
		$expectedVersion = trim( file_get_contents( __DIR__ . '/../../../../VERSION' ) );
		$this->assertSame( $expectedVersion, Version::getVersion() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\Version::getVersion
	 */
	public function testGetVersionIsTrimmed(): void {
		$version = Version::getVersion();
		$this->assertSame( trim( $version ), $version );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\Version::getVersion
	 */
	public function testGetVersionIsStable(): void {
		$first = Version::getVersion();
		$second = Version::getVersion();
		$this->assertSame( $first, $second );
	}
}
