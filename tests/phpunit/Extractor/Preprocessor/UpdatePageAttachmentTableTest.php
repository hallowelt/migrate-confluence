<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class UpdatePageAttachmentTableTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesPageAttachmentEntry(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 10, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 10, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 10, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'Expected one page attachment entry.' );
		$this->assertSame(
			601,
			$pageAttachments[0]['attachment_id'],
			'Unexpected attachment_id for page attachment.'
		);
		$this->assertSame(
			600,
			$pageAttachments[0]['page_id'],
			'Unexpected page_id for page attachment.'
		);
		$this->assertSame(
			'file.txt',
			$pageAttachments[0]['original_attachment_filename'],
			'Unexpected original filename for page attachment.'
		);
		$this->assertStringContainsString(
			'file.txt',
			$pageAttachments[0]['target_attachment_filename'],
			'Expected target filename to include original filename.'
		);
	}
}
