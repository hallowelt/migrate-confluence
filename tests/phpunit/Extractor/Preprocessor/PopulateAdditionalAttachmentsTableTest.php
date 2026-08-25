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
	public function testCreatesTargetAttachmentFilenameWithEmptyMigrationConfig(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'empty-config: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST_extra.pdf';
		$message = "empty-config: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepo(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'ext-ns-file-repo-compat: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST:extra.pdf';
		$message = "ext-ns-file-repo-compat: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			]
		] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'space-prefix mapping: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_extra.pdf';
		$message = "space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:extra.pdf';
		$message = "ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			]
		] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'space-prefix mapping: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_extra.pdf';
		$message = "space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		$workspaceDB->addAttachment(
			801, 1000, 'known.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);
		$workspaceDB->addAttachment(
			802, 1000, 'extra.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);
		$workspaceDB->addPageAttachment( 801, 800, 'known.pdf', 'TEST_Page-known.pdf' );

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one additional attachment entry.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:extra.pdf';
		$message = "ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testOrphanAttachmentWithoutSpaceFallsBackToContainerPageSpace(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 800, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );

		// Older Confluence export format: no "space" property on the orphan attachment itself,
		// but its container page (800) is known and has a space.
		$workspaceDB->addAttachment(
			803, null, 'orphan.pdf', 'pdf', 800, 'current', '1', '', '', -1, '/tmp/e', [], [], []
		);

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			1,
			$additionalAttachments,
			'Orphan attachment without space_id but with a resolvable container must not be dropped.'
		);
		$actualTargetFilename = (string)$additionalAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST_orphan.pdf';
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			'Expected the orphan attachment to inherit the space of its container page.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable::execute
	 */
	public function testOrphanAttachmentWithoutSpaceAndUnresolvableContainerIsSkippedAndLogged(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		// No space property and no known container (container_id -1, no page/blog post for it).
		$workspaceDB->addAttachment(
			804, null, 'unassignable.pdf', 'pdf', -1, 'current', '1', '', '', -1, '/tmp/f', [], [], []
		);

		$processor = new PopulateAdditionalAttachmentsTable( $workspaceDB, $dbLog, $writer, new MigrationConfig( [] ) );
		$processor->execute();

		$additionalAttachments = $workspaceDB->getAdditionalAttachments();
		$this->assertCount(
			0,
			$additionalAttachments,
			'Attachment with no resolvable space must be skipped.'
		);

		$logEntries = $workspaceDB->getLogEntriesForStep( 'extract', 'warning' );
		$found = false;
		foreach ( $logEntries as $entry ) {
			if ( str_contains( $entry['text'], '804' ) ) {
				$found = true;
				break;
			}
		}
		$this->assertTrue( $found, 'Expected a warning log entry about the unresolvable attachment.' );
	}
}
