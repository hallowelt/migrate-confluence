<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Converter\Processor\Widget;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class WidgetTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Widget::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/widget-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$processor = new Widget();
			$processor->process( $dom );

			$actualOutput = $dom->saveXML( $dom->documentElement );
			$expectedOutput = file_get_contents( "$dir/widget-output.xml" );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
