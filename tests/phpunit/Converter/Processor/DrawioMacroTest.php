<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use PHPUnit\Framework\TestCase;

class DrawioMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @var ConversionDataWriter
	 */
	private $conversionDataWriter;

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/drawio-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/drawio-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\StructuredMacroProcessor::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---drawio.png' => 'SomePage_drawio.png',
				'0---SomePage---SomeImage2.png' => 'SomePage_SomeImage2.png',
				'23---SomePage---drawio.png' => 'DEVOPS_SomePage_drawio.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[],
			[]
		);
		$this->conversionDataWriter = new ConversionDataWriter( [] );

		/** SpaceId GENERAL */
		$this->doTest( 0, false, 'drawio-macro-input.xml', 'drawio-macro-output-1.xml' );
		$this->doTest( 0, true, 'drawio-macro-input.xml', 'drawio-macro-output-1.xml' );

		/** Random SpaceId */
		$this->doTest( 23, false, 'drawio-macro-input.xml', 'drawio-macro-output-2.xml' );
		$this->doTest( 23, true, 'drawio-macro-input.xml', 'drawio-macro-output-3.xml' );
	}

	private function doTest( $spaceId, $nsFileRepoCompat, $input, $output ) {
		$input = file_get_contents( dirname( dirname( __DIR__ ) ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( dirname( __DIR__ ) ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DrawioMacro( $this->dataLookup, $this->conversionDataWriter,
			$spaceId, 'SomePage', $nsFileRepoCompat );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
