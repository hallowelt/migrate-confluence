<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Labelling;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Labelling;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class LabellingTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Labelling::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new Labelling( new AnalyzeDirectDataWriter( $this->workspaceDB ) );
		$this->executeProcessorForClass( $processor, __DIR__ . '/labelling.xml', 'Labelling' );

		$labellings = $this->workspaceDB->getLabellings();
		$this->assertCount( 1, $labellings, 'Expected exactly one labelling row.' );

		$labelling = $labellings[0];
		$this->assertSame( 61, $labelling['labelling_id'], 'Unexpected labelling_id value.' );
		$this->assertSame( 60, $labelling['label_id'], 'Unexpected label_id value.' );

		$properties = json_decode( $labelling['properties'], true );
		$this->assertSame( '60', $properties['label'], 'Unexpected properties.label value.' );
	}
}
