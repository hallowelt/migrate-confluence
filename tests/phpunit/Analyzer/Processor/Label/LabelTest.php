<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Label;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Label;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class LabelTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Label::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Label( new AnalyzeDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/label.xml', 'Label' );

		$labels = $this->workspaceDB->getLabels();
		$this->assertCount( 1, $labels, 'Expected exactly one label row.' );

		$label = $labels[0];
		$this->assertSame( 60, $label['label_id'], 'Unexpected label_id value.' );
		$this->assertSame( 'release', $label['name'], 'Unexpected name value.' );
		$this->assertSame( 'global', $label['namespace'], 'Unexpected namespace value.' );

		$properties = json_decode( $label['properties'], true );
		$this->assertSame( 'release', $properties['name'], 'Unexpected properties.name value.' );
		$this->assertSame( 'global', $properties['namespace'], 'Unexpected properties.namespace value.' );
	}
}
