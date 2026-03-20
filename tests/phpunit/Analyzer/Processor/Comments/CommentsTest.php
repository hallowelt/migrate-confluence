<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\Comments;

use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use PHPUnit\Framework\TestCase;
use XMLReader;

class CommentsTest extends TestCase {

	/**
	 * @param string $xmlFile
	 * @param array $data
	 * @return Comments
	 */
	private function runProcessor( string $xmlFile, array $data ): Comments {
		$xmlReader = new XMLReader();
		$xmlReader->open( $xmlFile );

		$processor = new Comments();
		$processor->setData( $data );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				$read = $xmlReader->read();
				continue;
			}

			$class = $xmlReader->getAttribute( 'class' );
			if ( $class === 'Comment' ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		return $processor;
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testPageLevelCommentIsStored() {
		$processor = $this->runProcessor(
			__DIR__ . '/comment_page_level.xml',
			[
				'analyze-inline-comment-ids' => [],
				'analyze-body-content-id-to-comment-id-map' => [ '800' => '600' ],
				'global-page-id-to-comment-ids-map' => [],
				'global-comment-id-to-metadata-map' => [],
				'global-body-content-id-to-comment-id-map' => [],
			]
		);

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
		$processor = $this->runProcessor(
			__DIR__ . '/comment_inline.xml',
			[
				'analyze-inline-comment-ids' => [ '601' ],
				'analyze-body-content-id-to-comment-id-map' => [ '801' => '601' ],
				'global-page-id-to-comment-ids-map' => [],
				'global-comment-id-to-metadata-map' => [],
				'global-body-content-id-to-comment-id-map' => [],
			]
		);

		$commentIdsMap = $processor->getData( 'global-page-id-to-comment-ids-map' );
		$this->assertSame( [], $commentIdsMap );

		$metadataMap = $processor->getData( 'global-comment-id-to-metadata-map' );
		$this->assertSame( [], $metadataMap );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\Comments::doExecute
	 */
	public function testCommentWithoutBodyContentIsSkipped() {
		$processor = $this->runProcessor(
			__DIR__ . '/comment_page_level.xml',
			[
				'analyze-inline-comment-ids' => [],
				// No entry for comment 600 in the body content map
				'analyze-body-content-id-to-comment-id-map' => [],
				'global-page-id-to-comment-ids-map' => [],
				'global-comment-id-to-metadata-map' => [],
				'global-body-content-id-to-comment-id-map' => [],
			]
		);

		$commentIdsMap = $processor->getData( 'global-page-id-to-comment-ids-map' );
		$this->assertSame( [], $commentIdsMap );
	}
}
