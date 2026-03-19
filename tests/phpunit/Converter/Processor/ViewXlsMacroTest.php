<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\ViewXlsMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class ViewXlsMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/view-xls-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/view-xls-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ViewXlsMacro::preprocess
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
				'0---SomePage---Dummy_1.pdf' => 'SomePage_Dummy_1.pdf',
				'0---SomePage---Dummy_2.doc' => 'SomePage_Dummy_2.doc',
				'0---SomePage---Dummy_3.xls' => 'SomePage_Dummy_3.xls',
				'23---SomePage---Dummy_1.pdf' => 'DEVOPS:SomePage_Dummy_1.pdf',
				'23---SomePage---Dummy_2.doc' => 'DEVOPS:SomePage_Dummy_2.doc',
				'23---SomePage---Dummy_3.xls' => 'DEVOPS:SomePage_Dummy_3.xls',
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

		/** SpaceId GENERAL */
		$this->doTest(
			0, "SomePage", 'view-xls-macro-input.xml', 'view-xls-macro-output-1.xml'
		);

		/** Random SpaceId */
		$this->doTest(
			23, "SomePage", 'view-xls-macro-input.xml', 'view-xls-macro-output-2.xml'
		);
	}

	/**
	 * @param int $spaceId
	 * @param string $pageName
	 * @param string $input
	 * @param string $output
	 */
	private function doTest( $spaceId, $pageName, $input, $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new ViewXlsMacro( $this->dataLookup, $spaceId, $pageName );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
