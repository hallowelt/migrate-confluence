<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsLinkProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class AttachmentsLinkTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::preprocess
		 * @return void
		 */
		public function testPreprocess() {
			$dir = dirname( dirname( __DIR__ ) ) . '/data';
			$input = file_get_contents( "$dir/attachmentslinktest-input.xml" );

			$dom = new DOMDocument();
			$dom->loadXML( $input );

			$currentSpaceId = 42;
			$currentRawPagename = 'SomePage';
			$dataLookup = new ConversionDataLookup(
				[
					42 => '',
					23 => 'DEVOPS'
				],
				[],
				[
					'42---SomePage---SomeImage.png' => 'SomePage_SomeImage.png',
					'42---SomePage---SomeImage1.png' => 'SomePage_SomeImage1.png',
					'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
				],
				[],
				[]
			);

			$processor = new AttachmentsLinkProcessor( $dataLookup, $currentSpaceId, $currentRawPagename, false );
			$processor->process( $dom );

			$actualOutput = $dom->saveXML();
			$expectedOutput = $input = file_get_contents( "$dir/attachmentslinktest-output.xml" );

			$this->assertEquals( $expectedOutput, $actualOutput );
		}
}
