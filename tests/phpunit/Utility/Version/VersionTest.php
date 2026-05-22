<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility;

use HalloWelt\MigrateConfluence\Utility\Version;
use PHPUnit\Framework\TestCase;

class VersionTest extends TestCase {

	public function testGetVersionReturnsNonemptyString(): void {
		$version = Version::getVersion();
		$this->assertIsString( $version );
		$this->assertNotEmpty( $version );
	}

	public function testGetVersionMatchesVersionFile(): void {
		$expectedVersion = trim( file_get_contents( __DIR__ . '/../../../../VERSION' ) );
		$this->assertSame( $expectedVersion, Version::getVersion() );
	}

	public function testGetVersionIsTrimmed(): void {
		$version = Version::getVersion();
		$this->assertSame( trim( $version ), $version );
	}

	public function testGetVersionIsStable(): void {
		$first = Version::getVersion();
		$second = Version::getVersion();
		$this->assertSame( $first, $second );
	}
}
