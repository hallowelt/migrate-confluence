<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateAttachmentsTableWithSpaceIdFallback;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateAttachmentsTableWithSpaceIdFallback
 */
class UpdateAttachmentsTableWithSpaceIdFallbackTest extends TestCase {

	use PreprocessorTestHelper;

	public function testBackfillsSpaceIdFromContainerPage(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addPage( 600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );
		// Older Confluence export format: no "space" property on the attachment itself.
		$workspaceDB->addAttachment(
			601, null, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/a', [], [], []
		);

		( new UpdateAttachmentsTableWithSpaceIdFallback( $workspaceDB, $dbLog, $writer ) )->execute();

		$attachments = $workspaceDB->getAttachments();
		$attachment = $this->findRowById( $attachments, 'attachment_id', 601 );
		$this->assertNotNull( $attachment );
		$this->assertSame( '1000', (string)$attachment['space_id'] );
	}

	public function testBackfillsSpaceIdFromContainerBlogPost(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Post', 'TEST:Post', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, null, 'file.txt', 'txt', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		( new UpdateAttachmentsTableWithSpaceIdFallback( $workspaceDB, $dbLog, $writer ) )->execute();

		$attachments = $workspaceDB->getAttachments();
		$attachment = $this->findRowById( $attachments, 'attachment_id', 701 );
		$this->assertNotNull( $attachment );
		$this->assertSame( '1000', (string)$attachment['space_id'] );
	}

	public function testDoesNotOverwriteExistingSpaceId(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
		$workspaceDB->addSpace( 2000, 'OTHER', 'Other Space', 'OTHER', '', '', -1, -1 );
		$workspaceDB->addPage( 600, 1000, 'Page', 'TEST:Page', 'current', '', '', '1', -1, -1, [], [], [], [] );
		// Attachment already has its own (different) space_id set explicitly.
		$workspaceDB->addAttachment(
			602, 2000, 'file.txt', 'txt', 600, 'current', '1', '', '', -1, '/tmp/c', [], [], []
		);

		( new UpdateAttachmentsTableWithSpaceIdFallback( $workspaceDB, $dbLog, $writer ) )->execute();

		$attachments = $workspaceDB->getAttachments();
		$attachment = $this->findRowById( $attachments, 'attachment_id', 602 );
		$this->assertNotNull( $attachment );
		$this->assertSame( '2000', (string)$attachment['space_id'] );
	}

	public function testLeavesUnresolvableAttachmentSpaceIdNull(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );
		$writer = $this->createWriter( $workspaceDB );

		// No space property and no known container (container_id -1, no page/blog post for it).
		$workspaceDB->addAttachment(
			603, null, 'file.txt', 'txt', -1, 'current', '1', '', '', -1, '/tmp/d', [], [], []
		);

		( new UpdateAttachmentsTableWithSpaceIdFallback( $workspaceDB, $dbLog, $writer ) )->execute();

		$attachments = $workspaceDB->getAttachments();
		$attachment = $this->findRowById( $attachments, 'attachment_id', 603 );
		$this->assertNotNull( $attachment );
		$this->assertNull( $attachment['space_id'] );
	}
}
