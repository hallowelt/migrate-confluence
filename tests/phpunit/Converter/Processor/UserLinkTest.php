<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class UserLinkTest extends ProcessorTestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\UserLink::preprocess
	 * @return void
	 */
	public function testProcess(): void {
		$dir = dirname(  __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/userlinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$processor = new UserLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/userlinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
