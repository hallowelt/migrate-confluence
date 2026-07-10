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
	public function testCreatesTargetAttachmentFilenameWithEmptyMigrationConfig(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'empty-config: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST_Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"empty-config: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepo(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'ext-ns-file-repo-compat: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST:Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"ext-ns-file-repo-compat: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			]
		] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'space-prefix mapping: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"space-prefix mapping: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			]
		] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'space-prefix mapping: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"space-prefix mapping: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addPage(
			600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], []
		);
		$workspaceDB->addAttachment(
			601, 1000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		$processor = new UpdatePageAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$pageAttachments = $workspaceDB->getPageAttachments();
		$this->assertCount( 1, $pageAttachments, 'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one page attachment entry.' );
		$actualTargetFilename = (string)$pageAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:Page-file.txt';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename. Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

}
