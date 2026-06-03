<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;
use PHPUnit\Framework\TestCase;

class GliffyMacroTest extends TestCase {
	/** @var DBConversionDataLookup */
	private $dataLookup;

	/** @var WorkspaceDB */
	private $workspaceDB;

	/** @var PipeToDB */
	private $pipeToDB;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GliffyMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->workspaceDB = ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat();
		$this->dataLookup = new DBConversionDataLookup( $this->workspaceDB );
		$pipe = fopen( 'php://temp', 'r+' );
		$this->pipeToDB = new PipeToDB( $pipe );

		$this->doTest( 0, 'gliffy-macro-input.xml', 'gliffy-macro-output-1.xml' );
		$this->doTest( 23, 'gliffy-macro-input.xml', 'gliffy-macro-output-2.xml' );

		rewind( $pipe );
		$this->assertStringContainsString( '"addGliffy"', stream_get_contents( $pipe ) );
		fclose( $pipe );
	}

	/**
	 * @param int $spaceId
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( int $spaceId, string $input, string $output ) {
		$input = file_get_contents( dirname( __DIR__, 2 ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new GliffyMacro(
			$this->dataLookup,
			$spaceId,
			'SomePage',
			$this->pipeToDB
		);
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
