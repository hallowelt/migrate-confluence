<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\InlineCommentMarkerMacro;
use PHPUnit\Framework\TestCase;

class InlineCommentMarkerMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\InlineCommentMarkerMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/inline-comment-marker-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new InlineCommentMarkerMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/inline-comment-marker-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
