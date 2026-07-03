<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AttachmentLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;
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
	 * @return void
	 */
	private function doTestAttachments( string $input, string $output ): void {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/$input" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new AttachmentLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/$output" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
