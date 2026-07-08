<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ContentProperty;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class ContentPropertyTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new ContentProperty( new AnalyzeDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass(
			$processor,
			__DIR__ . '/content_property_inline_comment.xml',
			'ContentProperty'
		);

		$contentProperties = $this->workspaceDB->getContentProperties();
		$this->assertCount( 1, $contentProperties, 'Expected exactly one content property row.' );

		$contentProperty = $contentProperties[0];
		$this->assertSame( 1001, $contentProperty['property_id'], 'Unexpected property_id value.' );
		$this->assertSame( 'inline-comment', $contentProperty['property_name'], 'Unexpected property_name value.' );
		$this->assertSame( 'Comment', $contentProperty['content_class'], 'Unexpected content_class value.' );

		$properties = json_decode( $contentProperty['properties'], true );
		$this->assertSame( 'inline-comment', $properties['name'], 'Unexpected properties.name value.' );
		$this->assertSame( '500', $properties['content'], 'Unexpected properties.content value.' );
		$this->assertSame( 'true', $properties['stringValue'], 'Unexpected properties.stringValue value.' );
	}
}
