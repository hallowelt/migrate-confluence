<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Emoticon;
use PHPUnit\Framework\TestCase;

class EmoticonTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\EmoticonProcessor::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
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
