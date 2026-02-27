<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ParentPages;

use DOMDocument;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class ParentPagesTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages::doExecute
	 */
	public function testParentPageIdIsStoredAsInt() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/parent_page.xml' );

		$processor = new ParentPages();
		$processor->setOutput( new NullOutput() );
		$processor->execute( $dom );

		$map = $processor->getData( 'analyze-page-id-to-parent-page-id-map' );
		$this->assertArrayHasKey( 500, $map );
		$this->assertIsInt( $map[500], 'Parent page ID must be int, not string' );
		$this->assertSame( 600, $map[500] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages::doExecute
	 */
	public function testConfluenceTitleIsStored() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/parent_page.xml' );

		$processor = new ParentPages();
		$processor->setOutput( new NullOutput() );
		$processor->execute( $dom );

		$map = $processor->getData( 'analyze-page-id-to-confluence-title-map' );
		$this->assertArrayHasKey( 500, $map );
		$this->assertSame( 'Test Page', $map[500] );
	}
}
