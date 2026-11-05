<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ContentPermission;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermission;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermissionSet;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class ContentPermissionTest extends TestCase {
	use ProcessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermissionSet::doExecute
	 */
	public function testSetIsStored(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$processor = new ContentPermissionSet( new AnalyzerDirectDataWriter( $db ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/set.xml', 'ContentPermissionSet' );

		$sets = $db->getContentPermissionSets();
		$this->assertCount( 1, $sets );
		$this->assertSame( 123, (int)$sets[0]['set_id'] );
		$this->assertSame( 456, (int)$sets[0]['content_id'] );
		$this->assertSame( 'Edit', $sets[0]['permission_type'] );
		$this->assertSame( [ 789, 790 ], json_decode( $sets[0]['permission_ids'], true ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermission::doExecute
	 */
	public function testUserPermissionIsStored(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$processor = new ContentPermission( new AnalyzerDirectDataWriter( $db ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/user.xml', 'ContentPermission' );

		$rows = $db->getContentPermissions();
		$this->assertCount( 1, $rows );
		$this->assertSame( 789, (int)$rows[0]['permission_id'] );
		$this->assertSame( 123, (int)$rows[0]['set_id'] );
		$this->assertSame( 'user-key-1', $rows[0]['user_key'] );
		$this->assertSame( '', $rows[0]['group_name'] );
		$this->assertNotSame( '', $rows[0]['timestamp'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentPermission::doExecute
	 */
	public function testGroupPermissionIsStored(): void {
		$db = ( new WorkspaceDbMock() )->createEmpty();
		$processor = new ContentPermission( new AnalyzerDirectDataWriter( $db ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/group.xml', 'ContentPermission' );

		$rows = $db->getContentPermissions();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'developers', $rows[0]['group_name'] );
		$this->assertSame( '', $rows[0]['user_key'] );
		$this->assertSame( 0, (int)$rows[0]['set_id'] );
	}
}
