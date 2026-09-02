<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

class GliffyMacroTest extends ProcessorTestCase {

	/** @var ConversionDataReader */
	private $dataLookup;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro::process
	 * @return void
	 */
	public function testProcess() {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat();
		$this->dataLookup = new ConversionDataReader( $workspaceDB );

		$this->doTest( 0, 'gliffy-macro-input.xml', 'gliffy-macro-output-1.xml', 3 );
		$this->doTest( 23, 'gliffy-macro-input.xml', 'gliffy-macro-output-2.xml', 3 );
	}

	/**
	 * @param int $spaceId
	 * @param string $input
	 * @param string $output
	 * @param int $writerMethodCalls
	 * @return void
	 */
	private function doTest( int $spaceId, string $input, string $output, int $writerMethodCalls ) {
		$input = file_get_contents( dirname( __DIR__, 2 ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataWriter = $this->createMock( ConverterDirectDataWriter::class );
		$dataWriter->expects( $this->exactly( $writerMethodCalls ) )->method( 'addGliffy' );

		$processor = new GliffyMacro(
			$this->dataLookup,
			$spaceId,
			'SomePage',
			$dataWriter
		);

		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
