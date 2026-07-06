<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class InterwikiPageLinksTest extends TestCase {
	/**
	 * Ensure page links resolve against current page versions only.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::process
	 * @return void
	 */
	public function testInterwikiPageLinksFromCurrentPageVersions(): void {
		$xml = '<xml xmlns:ac="some" xmlns:ri="thing">'
			. '<div><ac:link><ri:page ri:content-title="Page 1" ri:space-key="SPC1" /></ac:link></div>'
			. '<div><ac:link><ri:page ri:content-title="Page 26" ri:space-key="SPC2" /></ac:link></div>'
			. '<div><ac:link><ri:page ri:content-title="Page 51" ri:space-key="SPC3" /></ac:link></div>'
			. '<div><ac:link><ri:page ri:content-title="Page 76" ri:space-key="SPC4" /></ac:link></div>'
			. '<div><ac:link><ri:page ri:content-title="Page 101" ri:space-key="SPC5" /></ac:link></div>'
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new PageLink(
			$dataLookup,
			1,
			'Page 1',
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( '[[Space_1:Main_page|Main page]]', $actualOutput );
		$this->assertStringContainsString( '[[wiki-space_2:Main_page|Main page]]', $actualOutput );
		$this->assertStringContainsString( '[[wiki-space_3:Main_page|Main page]]', $actualOutput );
		$this->assertStringContainsString( '[[wiki-space_4:Space_4|Space 4]]', $actualOutput );
		$this->assertStringContainsString( '[[wiki-space_4:Space_5|Space 5]]', $actualOutput );
		$this->assertStringNotContainsString( '[[Category:Broken_page_link]]', $actualOutput );
	}
}
