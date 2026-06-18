<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class AttachmentLinkTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AttatchmentsLinkProcessor::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->doTestAttachments(
			'attachmentlinktest-input.xml',
			'attachmentlinktest-output.xml',
			false
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @param bool $extNSFileRepo
	 * @return void
	 */
	private function doTestAttachments( string $input, string $output ): void {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$processor = new AttachmentLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
