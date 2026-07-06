<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BodyContents;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;
use XMLReader;

class BodyContentsTest extends TestCase {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testPageIdIsStoredAsInt() {
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$xmlReader = new XMLReader();
		$xmlReader->open( __DIR__ . '/body_content_page.xml' );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'BodyContent' ) {
				continue;
			}
			$processor = new BodyContents( new AnalyzeDirectDataWriter( $this->workspaceDB ) );

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$bodyContents = $this->workspaceDB->getBodyContents();
		$bodyContent = $bodyContents[0];

		$this->assertSame( 100, $bodyContent['body_content_id'] );
		$this->assertIsInt( $bodyContent['content_id'], 'Page ID in body-content-id-to-page-id-map must be int' );
		$this->assertSame( 200, $bodyContent['content_id'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testCommentIdIsStoredAsInt() {
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$xmlReader = new XMLReader();
		$xmlReader->open( __DIR__ . '/body_content_comment.xml' );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'BodyContent' ) {
				continue;
			}
			$processor = new BodyContents( new AnalyzeDirectDataWriter( $this->workspaceDB ) );

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$bodyContents = $this->workspaceDB->getBodyContents();
		$bodyContent = $bodyContents[0];

		$this->assertEquals( 'Comment', $bodyContent['class'] );
		$this->assertEquals( '{"content":"600"}', $bodyContent['properties'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testBlogPostIdIsStoredAsInt() {
		$workspaceDBMock = new WorkspaceDbMock();
		$this->workspaceDB = $workspaceDBMock->createEmpty();

		$xmlReader = new XMLReader();
		$xmlReader->open( __DIR__ . '/body_content_blog_post.xml' );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'BodyContent' ) {
				continue;
			}
			$processor = new BodyContents( new AnalyzeDirectDataWriter( $this->workspaceDB ) );

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$bodyContents = $this->workspaceDB->getBodyContents();
		$bodyContent = $bodyContents[0];

		$this->assertEquals( 'BlogPost', $bodyContent['class'] );
		$this->assertEquals( '{"content":"200"}', $bodyContent['properties'] );
	}
}
