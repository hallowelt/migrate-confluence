<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use PHPUnit\Framework\TestCase;

class GliffyMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @var ConversionDataWriter
	 */
	private $conversionDataWriter;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\GliffyMacro::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---gliffy-file-1.png' => 'SomePage_gliffy-file-1.png',
				'0---SomePage---gliffy-file-1' => 'SomePage_gliffy-file-1.unknown',
				'0---SomePage---gliffy-file-2.png' => 'SomePage_gliffy-file-2.png',
				'0---SomePage---gliffy-file-2' => 'SomePage_gliffy-file-2.unknown',
				'0---SomePage---gliffy-file-2.svg' => 'SomePage_gliffy-file-2.svg',
				'23---SomePage---gliffy-file-1.png' => 'DEVOPS:SomePage_gliffy-file-1.png',
				'23---SomePage---gliffy-file-1' => 'DEVOPS:SomePage_gliffy-file-1.unknown',
				'23---SomePage---gliffy-file-2.png' => 'DEVOPS:SomePage_gliffy-file-2.png',
				'23---SomePage---gliffy-file-2' => 'DEVOPS:SomePage_gliffy-file-2.unknown',
				'23---SomePage---gliffy-file-2.svg' => 'DEVOPS:SomePage_gliffy-file-2.svg',
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[]
		);
		$this->conversionDataWriter = new ConversionDataWriter( [] );

		/** SpaceId GENERAL */
		$this->doTest( 0, 'gliffy-macro-input.xml', 'gliffy-macro-output-1.xml' );

		/** Random SpaceId */
		$this->doTest( 23, 'gliffy-macro-input.xml', 'gliffy-macro-output-2.xml' );
	}

	private function doTest( $spaceId, $input, $output ) {
		$input = file_get_contents( dirname( __DIR__, 2 ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataBuckets = new DataBuckets( [ 'gliffy-map' ] );
		$processor = new GliffyMacro( $this->dataLookup, $this->conversionDataWriter,
			$spaceId, 'SomePage', $dataBuckets );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
