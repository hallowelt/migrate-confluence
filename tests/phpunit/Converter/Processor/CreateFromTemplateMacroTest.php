<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class CreateFromTemplateMacroTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->doTest(
			'create-from-template-macro-input.xml',
			'create-from-template-macro-output.xml'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testProcessWithSpacePrefix() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/create-from-template-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addPageTemplate( 123456, 'SomePage', 42, '', 'Template:ABC/SomePage' );
		$workspaceDB->addPageTemplate( 7890, 'SomeOtherPage', 23, '', 'Template:DEVOPS/SomeOtherPage' );

		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new CreateFromTemplateMacro( $dataLookup );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( 'Template:ABC/SomePage', $actualOutput );
		$this->assertStringContainsString( 'Template:DEVOPS/SomeOtherPage', $actualOutput );
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( $input, $output ): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addPageTemplate( 123456, 'SomePage', null, '', 'Template:SomePage' );
		$workspaceDB->addPageTemplate( 7890, 'SomeOtherPage', null, '', 'Template:SomeOtherPage' );

		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new CreateFromTemplateMacro( $dataLookup );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
