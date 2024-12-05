<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroAttachments;
use PHPUnit\Framework\TestCase;

class StructuredMacroAttachmentsTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroAttachments::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/attachments-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new StructuredMacroAttachments();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/attachments-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
