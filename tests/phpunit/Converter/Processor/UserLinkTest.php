<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLinkProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class UserLinkTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\UserLinkProcessor::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/userlinktest-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$currentSpaceId = 42;
			$currentRawPagename = 'SomePage';
			$dataLookup = new ConversionDataLookup(
				[
					42 => 'ABC',
					23 => 'DEVOPS'
				],
				[
					'42---Page Title' => 'ABC:Page_Title',
					'42---Page Title2' => 'ABC:Page_Title2',
					'42---Page Title3' => 'ABC:Page_Title3',
					'23---Page Title3' => 'DEVOPS:Page_Title3',
				],
				[],
				[],
				[]
			);

			$processor = new UserLinkProcessor( $dataLookup, $currentSpaceId, $currentRawPagename, false );
			$processor->process( $dom );

			$actualOutput = $dom->saveXML();
			$expectedOutput = $input = file_get_contents( "$dir/userlinktest-output.xml" );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
