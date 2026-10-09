<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class TableExcerptMacroTest extends ProcessorTestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptMacro::process
	 * @return void
	 */
	public function testProcess() {
		$input = '<xml xmlns:ac="sample_namespace"><p>'
			. '<ac:structured-macro ac:name="table-excerpt">'
			. '<ac:parameter ac:name="name">Table 1</ac:parameter>'
			. '<ac:parameter ac:name="atlassian-macro-output-type">BLOCK</ac:parameter>'
			. '<ac:rich-text-body><table><tbody><tr><td>Cell</td></tr></tbody></table></ac:rich-text-body>'
			. '</ac:structured-macro>'
			. '<ac:structured-macro ac:name="excerpt"><ac:rich-text-body><p>Not touched</p></ac:rich-text-body>'
			. '</ac:structured-macro></p></xml>';
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$placeholderManager = new PlaceholderManager();
		( new TableExcerptMacro( $placeholderManager ) )->process( $dom );
		$actual = $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );

		$this->assertStringContainsString(
			'<excerpt-block name="Table 1"><table><tbody><tr><td>Cell</td></tr></tbody></table></excerpt-block>',
			$actual
		);
		$this->assertStringContainsString( 'ac:name="excerpt"', $actual, 'Plain excerpt macro must be left alone' );
	}
}
