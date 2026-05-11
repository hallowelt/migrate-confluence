<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class CreateFromTemplateMacroTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->doTestAttachments(
			'create-from-template-macro-input.xml',
			'create-from-template-macro-output.xml'
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTestAttachments( $input, $output ): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[],
			[
				'42---SomePage---SomeImage.png' => 'ABC_SomePage_SomeImage.png',
				'42---SomePage---SomeImage1.png' => 'ABC_SomePage_SomeImage1.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[],
			[
				123456 => 'SomePage',
				7890 => 'SomeOtherPage'
			]
		);

		$processor = new CreateFromTemplateMacro( $dataLookup );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
