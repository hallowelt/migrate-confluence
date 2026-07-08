<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithWikiTitle;
use PHPUnit\Framework\TestCase;

class UpdateBlogPostsTableWithWikiTitleTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithWikiTitle::execute
	 */
	public function testBuildsWikiTitleForCurrentBlogPost(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addSpace( 42, 'TEST', 'Test Space', 'TEST:', -1, -1 );
		$workspaceDB->addBlogPost( 500, 42, 'Sample blog', '', 'current', '', '', '1', -1, [], [], [], [] );

		$processor = new UpdateBlogPostsTableWithWikiTitle( $workspaceDB, $dbLog );
		$processor->execute();

		$blogPost = $this->findRowById( $workspaceDB->getBlogPosts(), 'page_id', 500 );
		$this->assertNotNull( $blogPost, 'Expected blog post row to exist.' );
		$this->assertNotSame( '', $blogPost['wiki_title'], 'Expected wiki_title to be generated.' );
		$this->assertStringStartsWith(
			'Blog:TEST/',
			$blogPost['wiki_title'],
			'Expected generated blog post wiki_title to use Blog:TEST/ namespace prefix.'
		);
	}
}
