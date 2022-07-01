<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentsLink;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class AttachmentsLinkTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AttatchmentsLinkProcessor::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->doTestAttachments(
			'attachmentslinktest-input.xml',
			'attachmentslinktest-output.xml',
			false
		);

		$this->doTestAttachments(
			'attachmentslinktest-input.xml',
			'attachmentslinktest-ns-file-repo-output.xml',
			true
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @param boolean $extNSFileRepo
	 * @return void
	 */
	private function doTestAttachments( $input, $output, $extNSFileRepo = false ): void {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[
				'42---SomePage---SomeImage.png' => 'ABC_SomePage_SomeImage.png',
				'42---SomePage---SomeImage1.png' => 'ABC_SomePage_SomeImage1.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[]
		);

		$processor = new AttachmentsLink( $dataLookup, $currentSpaceId, $currentRawPagename, $extNSFileRepo );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
