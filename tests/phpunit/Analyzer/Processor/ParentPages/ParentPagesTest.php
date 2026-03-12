<?php

namespace HalloWelt\MigrateConfluence\Tests\Analyzer\Processor\ParentPages;

use HalloWelt\MigrateConfluence\Analyzer\IAnalyzerProcessor;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;
use XMLReader;

class ParentPagesTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages::doExecute
	 */
	public function testParentPageIdIsStoredAsInt() {
		$xmlReader = new XMLReader();
		$xmlReader->open( __DIR__ . '/parent_page.xml' );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'Page' ) {
				continue;
			}
			$processor = new ParentPages();

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$map = $processor->getData( 'analyze-page-id-to-parent-page-id-map' );
		$this->assertArrayHasKey( 500, $map );
		$this->assertIsInt( $map[500], 'Parent page ID must be int, not string' );
		$this->assertSame( 600, $map[500] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages::doExecute
	 */
	public function testConfluenceTitleIsStored() {
		$xmlReader = new XMLReader();
		$xmlReader->open( __DIR__ . '/parent_page.xml' );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( $class !== 'Page' ) {
				continue;
			}
			$processor = new ParentPages();

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		$map = $processor->getData( 'analyze-page-id-to-confluence-title-map' );
		$this->assertArrayHasKey( 500, $map );
		$this->assertSame( 'Test Page', $map[500] );
	}
}
