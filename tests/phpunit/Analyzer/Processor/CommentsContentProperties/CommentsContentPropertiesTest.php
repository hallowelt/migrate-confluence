<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\CommentsContentProperties;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties;
use PHPUnit\Framework\TestCase;

class CommentsContentPropertiesTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testInlineCommentPropertyIsDetected() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/content_property_inline_comment.xml' );

		$processor = new CommentsContentProperties();
		$processor->execute( $dom );

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertContains( 500, $ids );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testInlineMarkerRefPropertyIsDetected() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/content_property_inline_marker_ref.xml' );

		$processor = new CommentsContentProperties();
		$processor->execute( $dom );

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertContains( 501, $ids );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testPageCommentPropertyIsNotDetectedAsInline() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/content_property_page_comment.xml' );

		$processor = new CommentsContentProperties();
		$processor->execute( $dom );

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertEmpty( $ids );
	}
}
