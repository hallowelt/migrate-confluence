<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class InterwikiChildrenMacroTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro::process
	 * @return void
	 */
	public function testResolvesInterwikiChildrenRootsUsingCurrentPageVersions(): void {
		$xml = '<xml xmlns:ac="some" xmlns:ri="thing">'
			. '<div><ac:structured-macro ac:name="children"></ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="children">'
			. '<ac:parameter ac:name="page"><ac:link><ri:page ri:content-title="Page 26" ri:space-key="SPC2" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="children">'
			. '<ac:parameter ac:name="page"><ac:link><ri:page ri:content-title="Page 101" ri:space-key="SPC5" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="children">'
			. '<ac:parameter ac:name="page"><ac:link><ri:page ri:content-title="Unknown Page" ri:space-key="SPC3" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new ChildrenMacro( 1, 'Space_1:Main_page', $dataLookup );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( '{{SubpageList|page=Space 1:Main page}}', $actualOutput );
		$this->assertStringContainsString( '{{SubpageList|page=wiki-space 2:Main page}}', $actualOutput );
		$this->assertStringContainsString( '{{SubpageList|page=wiki-space 4:Space 5}}', $actualOutput );
		$this->assertStringContainsString(
			'{{SubpageList|page=Confluence_page---SPC3---Unknown_Page}}[[Category:Broken_macro/children]]',
			$actualOutput
		);
	}
}
