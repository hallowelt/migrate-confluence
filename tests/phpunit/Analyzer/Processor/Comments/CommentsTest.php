<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Comments;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use PHPUnit\Framework\TestCase;

class CommentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testPageLevelCommentIsStored() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/comment_page_level.xml' );

		$processor = new Comments();
		$processor->setData( [
			'analyze-inline-comment-ids' => [],
			'analyze-body-content-id-to-comment-id-map' => [ '800' => '600' ],
			'global-page-id-to-comment-ids-map' => [],
			'global-comment-id-to-metadata-map' => [],
			'global-body-content-id-to-comment-id-map' => [],
		] );
		$processor->execute( $dom );

		$commentIdsMap = $processor->getData( 'global-page-id-to-comment-ids-map' );
		$this->assertArrayHasKey( 700, $commentIdsMap );
		$this->assertContains( 600, $commentIdsMap[700] );

		$metadataMap = $processor->getData( 'global-comment-id-to-metadata-map' );
		$this->assertArrayHasKey( 600, $metadataMap );
		$this->assertSame( 800, $metadataMap[600]['body_content_id'] );
		$this->assertSame( 'userkey-abc', $metadataMap[600]['creator_key'] );

		$bodyContentMap = $processor->getData( 'global-body-content-id-to-comment-id-map' );
		$this->assertArrayHasKey( 800, $bodyContentMap );
		$this->assertSame( 600, $bodyContentMap[800] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testInlineCommentIsSkipped() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/comment_inline.xml' );

		$processor = new Comments();
		$processor->setData( [
			'analyze-inline-comment-ids' => [ '601' ],
			'analyze-body-content-id-to-comment-id-map' => [ '801' => '601' ],
			'global-page-id-to-comment-ids-map' => [],
			'global-comment-id-to-metadata-map' => [],
			'global-body-content-id-to-comment-id-map' => [],
		] );
		$processor->execute( $dom );

		$commentIdsMap = $processor->getData( 'global-page-id-to-comment-ids-map' );
		$this->assertEmpty( $commentIdsMap );

		$metadataMap = $processor->getData( 'global-comment-id-to-metadata-map' );
		$this->assertEmpty( $metadataMap );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testCommentWithoutBodyContentIsSkipped() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/comment_page_level.xml' );

		$processor = new Comments();
		$processor->setData( [
			'analyze-inline-comment-ids' => [],
			// No entry for comment 600 in the body content map
			'analyze-body-content-id-to-comment-id-map' => [],
			'global-page-id-to-comment-ids-map' => [],
			'global-comment-id-to-metadata-map' => [],
			'global-body-content-id-to-comment-id-map' => [],
		] );
		$processor->execute( $dom );

		$commentIdsMap = $processor->getData( 'global-page-id-to-comment-ids-map' );
		$this->assertEmpty( $commentIdsMap );
	}
}
