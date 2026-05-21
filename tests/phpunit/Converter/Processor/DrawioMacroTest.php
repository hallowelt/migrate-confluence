<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class DrawioMacroTest extends TestCase {
	/** @var DBConversionDataLookup */
	private $dataLookup;

	/** @var ConversionDataWriter */
	private $conversionDataWriter;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro::process
	 * @return void
	 */
	public function testPreprocess() {
		$tempDir = sys_get_temp_dir() . '/confluence-migration-drawio-test-' . uniqid();
		$this->conversionDataWriter = new ConversionDataWriter( $tempDir );
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$this->doTest( 0, 'drawio-macro-input.xml', 'drawio-macro-output-1.xml' );
		$this->doTest( 23, 'drawio-macro-input.xml', 'drawio-macro-output-2.xml' );
	}

	/**
	 * @param int $spaceId
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( int $spaceId, string $input, string $output ) {
		$input = file_get_contents( dirname( __DIR__, 2 ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DrawioMacro( $this->dataLookup, $this->conversionDataWriter, $spaceId, 'SomePage' );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
