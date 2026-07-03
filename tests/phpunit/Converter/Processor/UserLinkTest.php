<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\UserLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;
use PHPUnit\Framework\TestCase;

class UserLinkTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\UserLink::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/userlinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new UserLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/userlinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
