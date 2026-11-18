<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\LogSkippedDeletedAttachments;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class LogSkippedDeletedAttachmentsTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\LogSkippedDeletedAttachments::execute
	 */
	public function testDeletedHeadAndItsHistoryAreSkippedAndLogged(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'deleted', '3', '', '', -1, '/tmp/a', [], [], []
		);
		$workspaceDB->addAttachment(
			602, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', 601, '/tmp/b', [], [], []
		);
		$workspaceDB->addAttachment(
			603, 1000, 'file.txt', 'txt', 600, 'current', '2', '', '', 601, '/tmp/c', [], [], []
		);

		( new LogSkippedDeletedAttachments( $workspaceDB, $writer ) )->execute();
		( new UpdatePageAttachmentTable( $workspaceDB, $writer, new MigrationConfig( [] ) ) )->execute();

		$this->assertSame( [], $workspaceDB->getPageAttachments() );
		$entries = $workspaceDB->getLogEntriesForStep( 'extract', 'notice' );
		$this->assertCount( 1, $entries );
		$this->assertStringContainsString( 'Attachment 601 is deleted; skipping its 2 history', $entries[0]['text'] );
	}
}
