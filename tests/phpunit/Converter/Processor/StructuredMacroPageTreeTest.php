<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroPageTree;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class StructuredMacroPageTreeTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroPageTree::process
	 * @return void
	 */
	public function testProcess() {
		$this->dataLookup = new ConversionDataLookup(
			[
				0 => 'GENERAL',
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[
				'23---Main_Page' => 'DEVOPS:Main Page',
				'23---Testpage' => 'DEVOPS:Testpage',
				'42---Main_Page' => 'ABC:Main Page',
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
				'42---Testpage' => 'ABC:SomeLinkedPage/Testpage',
			],
			[
				'0---SomePage---Dummy_1.pdf' => 'SomePage_Dummy_1.pdf'
			],
			[],
			[],
			[],
			[
				'DEVOPS' => 'DEVOPS'
			]
		);

		/** SpaceId GENERAL */
		$this->doTest( 'structuredmacro-pagetree-input.xml', 'structuredmacro-pagetree-output.xml' );
	}

	/**
	 * @param string $input
	 * @param string $output
	 */
	private function doTest( $input, $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new StructuredMacroPageTree( $this->dataLookup, 42, 'Testpage', 'Main Page' );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
