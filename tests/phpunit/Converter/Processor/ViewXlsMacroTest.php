<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\ViewXlsMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ViewXlsMacroTest extends TestCase {
	/**
	 * @var mixed
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
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

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
		$processor = new ViewXlsMacro( $this->dataLookup, $spaceId, $pageName, new MigrationConfig( [] ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
