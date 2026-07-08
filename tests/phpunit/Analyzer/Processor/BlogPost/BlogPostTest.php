<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BlogPost;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ProcessorTestHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class BlogPostTest extends TestCase {
	use ProcessorTestHelper;

	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost::doExecute
	 */
	public function testAllDatabaseFieldsAreStored(): void {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$processor = new BlogPost(
			new AnalyzeDirectDataWriter( $this->workspaceDB ),
			new MigrationConfig( [] )
		);
		$this->executeProcessorForClass( $processor, __DIR__ . '/blog_post.xml', 'BlogPost' );

		$blogPosts = $this->workspaceDB->getBlogPosts();
		$this->assertCount( 1, $blogPosts, 'Expected exactly one blog post row.' );

		$blogPost = $blogPosts[0];
		$this->assertSame( 262251, $blogPost['page_id'], 'Unexpected page_id value.' );
		$this->assertSame( 32973, $blogPost['space_id'], 'Unexpected space_id value.' );
		$this->assertSame( 'Our new tool', $blogPost['confluence_title'], 'Unexpected confluence_title value.' );
		$this->assertSame( '', $blogPost['wiki_title'], 'Unexpected wiki_title value.' );
		$this->assertSame( 'current', $blogPost['content_status'], 'Unexpected content_status value.' );
		$this->assertSame( '1', $blogPost['version'], 'Unexpected version value.' );
		$this->assertSame( -1, $blogPost['original_version_id'], 'Unexpected original_version_id value.' );
		$this->assertSame(
			date( 'YmdHis', strtotime( '2020-11-09 16:07:42.492' ) ),
			$blogPost['revision_timestamp'],
			'Unexpected revision_timestamp value.'
		);
		$this->assertSame( '[]', $blogPost['historical_ids'], 'Unexpected historical_ids value.' );
		$this->assertSame( '', $blogPost['last_modifier'], 'Unexpected last_modifier value.' );
		$this->assertSame( '["262252"]', $blogPost['body_content_ids'], 'Unexpected body_content_ids value.' );

		$properties = json_decode( $blogPost['properties'], true );
		$this->assertSame( 'Our new tool', $properties['title'], 'Unexpected properties.title value.' );
		$this->assertSame( '1', $properties['version'], 'Unexpected properties.version value.' );
		$this->assertSame( 'current', $properties['contentStatus'], 'Unexpected properties.contentStatus value.' );

		$collection = json_decode( $blogPost['collection'], true );
		$this->assertSame( [ '262252' ], $collection['bodyContents'], 'Unexpected collection.bodyContents value.' );
	}
}
