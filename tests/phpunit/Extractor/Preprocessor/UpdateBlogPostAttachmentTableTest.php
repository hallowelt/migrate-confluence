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
	public function testCreatesBlogPostAttachmentEntry(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 700, 42, 'Blog', 'Blog:TEST/Blog', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addAttachment(
			701, 42, 'image.png', 'png', 700, 'current', '1', '', '', -1, '/tmp/b', [], [], []
		);

		$processor = new UpdateBlogPostAttachmentTable( $workspaceDB, $dbLog, new MigrationConfig( [] ) );
		$processor->execute();

		$blogPostAttachments = $workspaceDB->getBlogPostAttachments();
		$this->assertCount( 1, $blogPostAttachments, 'Expected one blog post attachment entry.' );
		$this->assertSame(
			701,
			$blogPostAttachments[0]['attachment_id'],
			'Unexpected attachment_id for blog post attachment.'
		);
		$this->assertSame(
			700,
			$blogPostAttachments[0]['blog_post_id'],
			'Unexpected blog_post_id for blog post attachment.'
		);
		$this->assertSame(
			'image.png',
			$blogPostAttachments[0]['original_attachment_filename'],
			'Unexpected original filename for blog post attachment.'
		);
		$this->assertStringContainsString(
			'image.png',
			$blogPostAttachments[0]['target_attachment_filename'],
			'Expected target filename to include original filename.'
		);
	}
}
