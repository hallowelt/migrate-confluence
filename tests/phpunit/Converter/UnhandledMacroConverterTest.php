<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class UnhandledMacroConverterTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\UnhandledMacroConverter::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---Dummy_1.pdf' => 'SomePage_Dummy_1.pdf',
				'0---SomePage---Dummy_2.docx' => 'SomePage_Dummy_2.docx',
				'0---SomePage---Dummy_3.png' => 'SomePage_Dummy_3.png',
				'23---SomePage---Dummy_1.pdf' => 'DEVOPS:SomePage_Dummy_1.pdf',
				'23---SomePage---Dummy_2.docx' => 'DEVOPS:SomePage_Dummy_2.docx',
				'23---SomePage---Dummy_3.png' => 'DEVOPS:SomePage_Dummy_3.png',
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

		$dir = dirname( __DIR__ ) . '/data';
		$input = file_get_contents( "$dir/unhandled-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new UnhandledMacroConverter( $dataLookup, 0, 'SomeLinkedPage' );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/unhandled-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
