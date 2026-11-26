<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\MigrationConfig;

use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\MigrationConfig::getHierarchyMethod
 */
class HierarchyMethodTest extends TestCase {

	public function testDefaultDependsOnProfile(): void {
		$this->assertSame( 'page-hierarchies', ( new MigrationConfig( [] ) )->getHierarchyMethod() );
		$this->assertSame(
			'enhanced-sidebar',
			( new MigrationConfig( [ 'profile' => 'mediawiki' ] ) )->getHierarchyMethod()
		);
	}

	public function testExplicitValueWins(): void {
		$config = new MigrationConfig( [ 'profile' => 'mediawiki', 'hierarchy-method' => 'none' ] );
		$this->assertSame( 'none', $config->getHierarchyMethod() );
	}

	public function testUnknownValueIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		( new MigrationConfig( [ 'hierarchy-method' => 'sidebar' ] ) )->getHierarchyMethod();
	}
}
