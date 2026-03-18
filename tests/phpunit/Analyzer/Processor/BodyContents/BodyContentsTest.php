<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BodyContents;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use PHPUnit\Framework\TestCase;
use XMLReader;

class BodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testPageIdIsStoredAsInt() {
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
			$processor = new BodyContents();

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$map = $processor->getData( 'analyze-body-content-id-to-page-id-map' );
		$this->assertArrayHasKey( 100, $map );
		$this->assertIsInt( $map[100], 'Page ID in body-content-id-to-page-id-map must be int' );
		$this->assertSame( 200, $map[100] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testCommentIdIsStoredAsInt() {
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
			$processor = new BodyContents();

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$map = $processor->getData( 'analyze-body-content-id-to-comment-id-map' );
		$this->assertArrayHasKey( 800, $map );
		$this->assertIsInt( $map[800], 'Comment ID in body-content-id-to-comment-id-map must be int' );
		$this->assertSame( 600, $map[800] );

		// Comment entries must NOT appear in the page map
		$pageMap = $processor->getData( 'analyze-body-content-id-to-page-id-map' );
		$this->assertArrayNotHasKey( 800, $pageMap );
	}
}
