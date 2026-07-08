<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ViewFileMacroTest extends ProcessorTestCase {
	/**
	 * @var mixed
	 */
	private $dataLookup;

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		/** SpaceId GENERAL */
		$this->doTest(
			0, "SomePage", 'view-file-macro-input.xml', 'view-file-macro-output-1.xml'
		);

		/** Random SpaceId */
		$this->doTest(
			23, "SomePage", 'view-file-macro-input.xml', 'view-file-macro-output-2.xml'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro::process
	 * @return void
	 */
	public function testProcessBrokenMacro() {
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		// Macros with no params at all, or with a name param but no ri:filename attribute,
		// must be replaced with the broken-macro category marker.
		$this->doTest(
			0, "SomePage", 'view-file-macro-broken-input.xml', 'view-file-macro-broken-output.xml'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\ViewFileMacro::process
	 * @return void
	 */
	public function testProcessUnmappedFilename() {
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		// ri:filename is present but not found in the migration map: the macro must still
		// render using the expected wiki filename (red link) and append Broken_attachment_link.
		$this->doTest(
			0, "SomePage", 'view-file-macro-unmapped-input.xml', 'view-file-macro-unmapped-output.xml'
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
		$processor = new ViewFileMacro( $this->dataLookup, $spaceId, $pageName, new MigrationConfig( [] ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
