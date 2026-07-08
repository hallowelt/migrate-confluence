<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsMacro;

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

		$processor = new AttachmentsMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/attachments-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
