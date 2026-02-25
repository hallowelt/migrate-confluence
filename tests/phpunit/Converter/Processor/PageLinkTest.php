<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class PageLinkTest extends TestCase {

		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::preprocess
		 * @return void
		 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

			$currentSpaceId = 42;
			$currentRawPagename = 'SomePage';
			$dataLookup = new ConversionDataLookup(
				// space-id-to-prefix-map
				[
					0 => '',
					42 => 'ABC',
					23 => 'DEVOPS'
				],
				// pages-titles-map
				[
					'42---Page_Title' => 'ABC:Page_Title',
					'42---Page_Title2' => 'ABC:Page_Title2',
					'42---Page_Title3' => 'ABC:Page_Title3',
					'42---Page_Title5' => 'ABC:Test/Page_Title5',
					'23---Page_Title3' => 'DEVOPS:Page_Title3',
					'23---Page_Title3,_Test' => 'DEVOPS:Page_Title3/Test',
					'0---Page_Title6' => 'Page_Title6',
					'0---Page_Title7' => 'Test/Page_Title7',
				],
				[],
				[],
				[],
				[],
				[
					0 => '',
					42 => 'ABC',
					23 => 'DEVOPS'
				]
			);

		$processor = new PageLink( $dataLookup, $currentSpaceId, $currentRawPagename, false );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/pagelinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
