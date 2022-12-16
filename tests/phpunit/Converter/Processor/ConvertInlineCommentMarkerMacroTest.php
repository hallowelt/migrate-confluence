<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertInlineCommentMarkerMacro;
use PHPUnit\Framework\TestCase;

class ConvertInlineCommentMarkerMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertInlineCommentMarkerMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/inlinecommentmarkermacro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ConvertInlineCommentMarkerMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/inlinecommentmarkermacro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
