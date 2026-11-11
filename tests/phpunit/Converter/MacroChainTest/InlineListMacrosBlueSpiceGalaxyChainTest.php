<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ChildrenMacro;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\SpaceDetailsMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

/**
 * @group full
 */
class InlineListMacrosBlueSpiceGalaxyChainTest extends MacroChainTestBase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\ChildrenMacro::process
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\SpaceDetailsMacro::process
	 * @dataProvider provideFixtures
	 */
	public function testMacroChain( string $inputFixture, string $expectedFixture, string $processor ): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$inputXml = (string)file_get_contents( "$dir/$inputFixture" );
		$expected = $this->applyConfluenceFinalReplacements(
			(string)file_get_contents( "$dir/BlueSpiceGalaxy/$expectedFixture" )
		);

		$actual = $this->runChainWithProcessor( $this->createProcessor( $processor ), $inputXml );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public function provideFixtures(): array {
		return [
			'children' => [ 'children-macro-input.xml', 'children-macro-output.wikitext', 'children' ],
			'space-details' => [ 'space-details-macro-input.xml', 'space-details-macro-output.wikitext', 'space' ],
		];
	}

	private function createProcessor( string $name ): IProcessor {
		$writer = $this->createConverterDataWriter();

		return match ( $name ) {
			'children' => new ChildrenMacro(
				$writer,
				42,
				'SomePage',
				new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() ),
				$this->placeholderManager
			),
			'space' => new SpaceDetailsMacro( $writer, 1, $this->placeholderManager ),
		};
	}
}
