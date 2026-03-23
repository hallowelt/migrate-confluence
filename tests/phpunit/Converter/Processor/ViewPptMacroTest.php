<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\ViewPdfMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\ViewPptMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class ViewPptMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/view-ppt-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/view-ppt-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ViewPptMacro::preprocess
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
				'0---SomePage---Dummy_1.ppt' => 'SomePage_Dummy_1.ppt',
				'0---SomePage---Dummy_2.doc' => 'SomePage_Dummy_2.doc',
				'0---SomePage---Dummy_3.xls' => 'SomePage_Dummy_3.xls',
				'23---SomePage---Dummy_1.ppt' => 'DEVOPS:SomePage_Dummy_1.ppt',
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
			0, "SomePage", 'view-ppt-macro-input.xml', 'view-ppt-macro-output-1.xml'
		);

		/** Random SpaceId */
		$this->doTest(
			23, "SomePage", 'view-ppt-macro-input.xml', 'view-ppt-macro-output-2.xml'
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
		$processor = new ViewPptMacro( $this->dataLookup, $spaceId, $pageName );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
