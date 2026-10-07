<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class AttachmentsMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/attachments-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$placeholderManager = new PlaceholderManager();
		$processor = new AttachmentsMacro( $dataLookup, 1, 'MyPage', $placeholderManager );
		$processor->process( $dom );

		$actualOutput = $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );
		$expectedOutput = file_get_contents( "$this->dir/attachments-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
