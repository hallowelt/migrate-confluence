<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class AnchorLinkTest extends ProcessorTestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\AnchorLink::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/anchorlinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$dataReader = new ConverterDirectDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$processor = new AnchorLink( $dataReader, 42, 'SomePage', new MigrationConfig( [] ) );
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
		$dataReader = new ConverterDirectDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$processor = new AnchorLink( $dataReader, 42, 'SomePage', new MigrationConfig( [] ) );
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
