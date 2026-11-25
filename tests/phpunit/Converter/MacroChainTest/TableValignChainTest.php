<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

/**
 * @group full
 */
class TableValignChainTest extends MacroChainTestBase {

	/**
	 * Newer pandoc versions prefix unknown HTML attributes with "data-"; valign must stay as is.
	 *
	 * @coversNothing
	 */
	public function testValignIsPreserved(): void {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = (string)file_get_contents( "$dir/table-valign-input.xml" );
		$expected = (string)file_get_contents( "$dir/table-valign-output.wikitext" );

		$this->assertSame( $expected, $this->runChainWithProcessor( [], $input ) );
	}
}
