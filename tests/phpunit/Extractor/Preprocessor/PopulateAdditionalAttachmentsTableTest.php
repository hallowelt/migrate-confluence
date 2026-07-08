<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class PopulateAdditionalAttachmentsTableTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testAddsOnlyUnknownCurrentAttachmentsToAdditionalTable(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 10, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage( 800, 10, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 10, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 10, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount( 1, $additionalAttachments, 'Expected one additional attachment entry.' );
		$this->assertSame(
			802,
			$additionalAttachments[0]['attachment_id'],
			'Expected only unknown attachment to be added.'
		);
		$this->assertSame(
			'extra.pdf',
			$additionalAttachments[0]['original_attachment_filename'],
			'Unexpected original filename for additional attachment.'
		);
		$this->assertNotSame(
			'',
			$additionalAttachments[0]['target_attachment_filename'],
			'Expected target filename for additional attachment.'
		);
	}
}
