<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\CommentsContentProperties;

use HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties;
use PHPUnit\Framework\TestCase;
use XMLReader;

class CommentsContentPropertiesTest extends TestCase {

	/**
	 * @param string $xmlFile
	 * @return CommentsContentProperties
	 */
	private function runProcessor( string $xmlFile ): CommentsContentProperties {
		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$processor = new CommentsContentProperties();

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'ContentProperty' ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		return $processor;
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testInlineCommentPropertyIsDetected() {
		$processor = $this->runProcessor(
			__DIR__ . '/content_property_inline_comment.xml'
		);

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertContains( 500, $ids );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testInlineMarkerRefPropertyIsDetected() {
		$processor = $this->runProcessor(
			__DIR__ . '/content_property_inline_marker_ref.xml'
		);

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertContains( 501, $ids );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\CommentsContentProperties::execute
	 */
	public function testPageCommentPropertyIsNotDetectedAsInline() {
		$processor = $this->runProcessor(
			__DIR__ . '/content_property_page_comment.xml'
		);

		$ids = $processor->getData( 'analyze-inline-comment-ids' );
		$this->assertSame( [], $ids );
	}
}
