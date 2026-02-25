<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class ViewFileMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro::process
	 * @return void
	 */
	public function testProcess() {
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
				'0---SomePage---Dummy_2.docx' => 'SomePage_Dummy_2.docx',
				'0---SomePage---Dummy_3.png' => 'SomePage_Dummy_3.png',
				'23---SomePage---Dummy_1.pdf' => 'DEVOPS_SomePage_Dummy_1.pdf',
				'23---SomePage---Dummy_2.docx' => 'DEVOPS_SomePage_Dummy_2.docx',
				'23---SomePage---Dummy_3.png' => 'DEVOPS_SomePage_Dummy_3.png',
			],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			]
		);

		/** SpaceId GENERAL */
		$this->doTest(
			0, "SomePage", false, 'view-file-macro-input.xml', 'view-file-macro-output-1.xml'
		);
		$this->doTest(
			0, "SomePage", true, 'view-file-macro-input.xml', 'view-file-macro-output-1.xml'
		);

		/** Random SpaceId */
		$this->doTest(
			23, "SomePage", false, 'view-file-macro-input.xml', 'view-file-macro-output-2.xml'
		);
		$this->doTest(
			23, "SomePage", true, 'view-file-macro-input.xml', 'view-file-macro-output-3.xml'
		);
	}

	/**
	 * @param int $spaceId
	 * @param string $pageName
	 * @param bool $nsFileRepoCompat
	 * @param string $input
	 * @param string $output
	 */
	private function doTest( $spaceId, $pageName, $nsFileRepoCompat, $input, $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new ViewFileMacro( $this->dataLookup, $spaceId, $pageName, $nsFileRepoCompat );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
