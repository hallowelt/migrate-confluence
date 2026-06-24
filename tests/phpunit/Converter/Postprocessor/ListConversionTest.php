<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixEmptyListItemWrapper;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * Tests that HTML list elements (<ul>, <ol>, <li>) are correctly converted to
 * MediaWiki wikitext via Pandoc.
 */
class ListConversionTest extends TestCase {

	/**
	 * @covers \HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML::doConvert
	 */
	public function testListConversion(): void {
		$dataDir = __DIR__ . '/../../data/lists/';

		$workspace = new Workspace( new SplFileInfo( sys_get_temp_dir() ) );
		$converter = new PandocHTML( [], $workspace );
		$postprocessor = new FixEmptyListItemWrapper();

		$result = $postprocessor->postprocess(
			$converter->convert( new SplFileInfo( $dataDir . 'input.html' ) )
		);
		$expected = file_get_contents( $dataDir . 'output.wiki' );

		$this->assertSame( $expected, $result );
	}
}
