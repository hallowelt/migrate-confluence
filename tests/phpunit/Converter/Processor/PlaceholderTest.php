<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Placeholder;

class PlaceholderTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Placeholder::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname(  __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/placeholder-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Placeholder();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/placeholder-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
