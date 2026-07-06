<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class InterwikiPageTreeMacroTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testResolvesInterwikiPageTreeRootsUsingCurrentPageVersions(): void {
		$xml = '<xml xmlns:ac="some" xmlns:ri="thing">'
			. '<div><ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:content-title="@home" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:content-title="Page 26" ri:space-key="SPC2" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:content-title="Unknown Page" ri:space-key="SPC3" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '<div><ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:content-title="@none" ri:space-key="SPC2" />'
			. '</ac:link></ac:parameter>'
			. '</ac:structured-macro></div>'
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new PageTreeMacro( $dataLookup, 1, 'Page 1', 'Space_1:Main_page' );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( 'content-title=Space_1:Main_page', $actualOutput );
		$this->assertStringContainsString( 'content-title=wiki-space_2:Main_page', $actualOutput );
		$this->assertStringContainsString( 'space-key=Space_2', $actualOutput );
		$this->assertStringContainsString( 'content-title=Unknown Page', $actualOutput );
		$this->assertStringContainsString( '[[Category:Broken_macro/pagetree]]', $actualOutput );
	}
}
