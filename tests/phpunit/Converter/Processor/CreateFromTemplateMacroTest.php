<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
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

		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addPageTemplate( 123456, 'SomePage', null, '' );
		$workspaceDB->addPageTemplate( 7890, 'SomeOtherPage', null, '' );

		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new CreateFromTemplateMacro( $dataLookup );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
