<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\InlineCommentMarker;
use PHPUnit\Framework\TestCase;

class InlineCommentMarkerTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\InlineCommentMarker::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/inline-comment-marker-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new InlineCommentMarker();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/inline-comment-marker-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
