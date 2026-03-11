<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\BodyContents;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use PHPUnit\Framework\TestCase;

class BodyContentsTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents::execute
	 */
	public function testPageIdIsStoredAsInt() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/body_content_page.xml' );

		$processor = new BodyContents();
		$processor->execute( $dom );

		$map = $processor->getData( 'global-body-content-id-to-page-id-map' );
		$this->assertArrayHasKey( 100, $map );
		$this->assertIsInt( $map[100], 'Page ID in body-content-id-to-page-id-map must be int' );
		$this->assertSame( 200, $map[100] );
	}
}
