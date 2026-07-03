<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;
use PHPUnit\Framework\TestCase;

class AnchorLinkTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$input = file_get_contents( "$dir/anchorlinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new AnchorLink( $dataLookup, 42, 'SomePage', new MigrationConfig( [] ), $wikiConfig );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/anchorlinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink::makeLink
	 * @dataProvider provideMakeLinkCases
	 * @return void
	 */
	public function testMakeLink( array $linkParts, string $expected ) {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );
		$processor = new AnchorLink( $dataLookup, 42, 'SomePage', new MigrationConfig( [] ), $wikiConfig );
		$this->assertSame( $expected, $processor->makeLink( $linkParts ) );
	}

	/**
	 * @return array
	 */
	public static function provideMakeLinkCases(): array {
		return [
			'anchor only' => [
				[ '#LoremIpsumAnker' ],
				'[[#LoremIpsumAnker]]',
			],
			'anchor with label' => [
				[ '#Section Heading', 'Click here' ],
				'[[#Section Heading|Click here]]',
			],
		];
	}
}
