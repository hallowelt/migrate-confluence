<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor\BlueSpiceGalaxy;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Tests\Converter\Processor\ProcessorTestCase;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class TableExcerptIncludeMacroTest extends ProcessorTestCase {

	private const PAGE_LINK = '<ac:link><ri:page ri:content-title="Some Confluence page name"/></ac:link>';

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy\TableExcerptIncludeMacro::process
	 * @dataProvider provideCases
	 * @param string $parameters
	 * @param string|null $currentPageTitle
	 * @param string $expected
	 * @return void
	 */
	public function testProcess( string $parameters, ?string $currentPageTitle, string $expected ) {
		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_namespace2"><p>'
			. '<ac:structured-macro ac:name="table-excerpt-include">' . $parameters . '</ac:structured-macro>'
			. '</p></xml>'
		);

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$placeholderManager = new PlaceholderManager();
		$processor = new TableExcerptIncludeMacro( $dataLookup, 42, $placeholderManager, $currentPageTitle );
		$processor->process( $dom );
		$actual = $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );

		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public static function provideCases(): array {
		$named = '<ac:parameter ac:name="name">Table 1</ac:parameter>';
		$include = '<excerpt-include showpanel="false" page="ABC:Some_MediaWiki_page_name" excerpt="Table 1"/>';

		return [
			'current page source' => [ $named, 'Some Confluence page name', $include ],
			'specific page source' => [
				$named . '<ac:parameter ac:name="page">' . self::PAGE_LINK . '</ac:parameter>',
				null,
				$include
			],
			'panel is always suppressed' => [
				$named . '<ac:parameter ac:name="nopanel">false</ac:parameter>',
				'Some Confluence page name',
				'showpanel="false"'
			],
			'current page unknown is broken' => [
				$named,
				null,
				'[[Category:Broken_macro/table-excerpt-include]]'
			],
		];
	}
}
