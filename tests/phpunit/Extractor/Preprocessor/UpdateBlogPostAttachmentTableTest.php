<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class UpdateBlogPostAttachmentTableTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithEmptyMigrationConfig(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount( 1, $blogPostAttachments, 'empty-config: expected exactly one blog post attachment entry.' );
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST_Blog-image.png';
		$message = "empty-config: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepo(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount(
			1,
			$blogPostAttachments,
			'ext-ns-file-repo-compat: expected exactly one blog post attachment entry.'
		);
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'TEST:Blog-image.png';
		$message = "ext-ns-file-repo-compat: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			]
		] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount(
			1,
			$blogPostAttachments,
			'space-prefix mapping: expected exactly one blog post attachment entry.'
		);
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_Blog-image.png';
		$message = "space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMapping(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount(
			1,
			$blogPostAttachments,
			'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one blog post attachment entry.'
		);
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:Blog-image.png';
		$message = "ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			]
		] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount(
			1,
			$blogPostAttachments,
			'space-prefix mapping: expected exactly one blog post attachment entry.'
		);
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST_Blog-image.png';
		$message = "space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable::execute
	 */
	public function testCreatesTargetAttachmentFilenameWithExtNsFileRepoAndSpaceMappingAndRootpages(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 1000, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 1000, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 1000, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [
			'space-prefix' => [
				'TEST' => 'MYTEST:Root/'
			],
			'ext-ns-file-repo-compat' => true
		] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount(
			1,
			$blogPostAttachments,
			'ext-ns-file-repo-compat + space-prefix mapping: expected exactly one blog post attachment entry.'
		);
		$actualTargetFilename = (string)$blogPostAttachments[0]['target_attachment_filename'];
		$expectedTargetFilename = 'MYTEST:Blog-image.png';
		$message = "ext-ns-file-repo-compat + space-prefix mapping: unexpected target_attachment_filename.";
		$this->assertSame(
			$expectedTargetFilename,
			$actualTargetFilename,
			"$message Expected '$expectedTargetFilename', got '$actualTargetFilename'."
		);
	}
}
