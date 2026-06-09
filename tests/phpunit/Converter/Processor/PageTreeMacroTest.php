<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class PageTreeMacroTest extends TestCase {
	/** @var DBConversionDataLookup */
	private $dataLookup;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dataLookup = $this->makeLookup();
		$this->doTest( 'pagetree-macro-input.xml', 'pagetree-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcessWithoutSpaceKey() {
		$this->dataLookup = $this->makeLookup();
		$this->doTest( 'pagetree-macro-no-spacekey-input.xml', 'pagetree-macro-no-spacekey-output.xml' );
	}

	/**
	 * @return DBConversionDataLookup
	 */
	private function makeLookup() {
		return new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( string $input, string $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new PageTreeMacro( $this->dataLookup, 42, 'Testpage', 'ABC:SomeLinkedPage/Testpage', 'Main Page' );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
