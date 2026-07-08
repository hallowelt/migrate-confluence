<?php

namespace HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBodyContentIdsFallback;
use HalloWelt\MigrateConfluence\Tests\Extractor\Preprocessor\PreprocessorTestHelper;
use PHPUnit\Framework\TestCase;

class UpdateBodyContentIdsFallbackTest extends TestCase {
	use PreprocessorTestHelper;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBodyContentIdsFallback::execute
	 */
	public function testFillsMissingBodyContentIdsForAllSupportedTables(): void {
		$workspaceDB = $this->createWorkspaceDB();
		$dbLog = $this->createDBLog( $workspaceDB );

		$workspaceDB->addPage( 300, 10, 'Page', '', 'current', '', '', '1', -1, -1, [], [], [], [] );
		$workspaceDB->addBlogPost( 301, 10, 'Blog', '', 'current', '', '', '1', -1, [], [], [], [] );
		$workspaceDB->addComment( 302, 300, 'Page', 'current', 'user-1', [], '', '', [] );
		$workspaceDB->addSpaceDescription( 303, 'current', '1', -1, '', [], [], [], [] );

		$workspaceDB->addBodyContent( 1300, 300, 'Page', [ 'content' => '300' ] );
		$workspaceDB->addBodyContent( 1301, 301, 'BlogPost', [ 'content' => '301' ] );
		$workspaceDB->addBodyContent( 1302, 302, 'Comment', [ 'content' => '302' ] );
		$workspaceDB->addBodyContent( 1303, 303, 'SpaceDescription', [ 'content' => '303' ] );

		$processor = new UpdateBodyContentIdsFallback( $workspaceDB, $dbLog );
		$processor->execute();

		$page = $this->findRowById( $workspaceDB->getPages(), 'page_id', 300 );
		$blogPost = $this->findRowById( $workspaceDB->getBlogPosts(), 'page_id', 301 );
		$comment = $this->findRowById( $workspaceDB->getComments(), 'comment_id', 302 );
		$spaceDescription = $this->findRowById( $workspaceDB->getSpaceDescriptions(), 'space_description_id', 303 );

		$this->assertSame( [ 1300 ], json_decode( $page['body_content_ids'], true ), 'Expected page body_content_ids fallback update.' );
		$this->assertSame( [ 1301 ], json_decode( $blogPost['body_content_ids'], true ), 'Expected blog post body_content_ids fallback update.' );
		$this->assertSame( [ 1302 ], json_decode( $comment['body_content_ids'], true ), 'Expected comment body_content_ids fallback update.' );
		$this->assertSame( [ 1303 ], json_decode( $spaceDescription['body_content_ids'], true ), 'Expected space description body_content_ids fallback update.' );
	}
}
