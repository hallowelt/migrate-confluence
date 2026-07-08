<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Emoticon;

class EmoticonTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\EmoticonProcessor::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname(  __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/emoticon-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new Emoticon();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$input = file_get_contents( "$dir/emoticon-output.xml" );
		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( $input );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
