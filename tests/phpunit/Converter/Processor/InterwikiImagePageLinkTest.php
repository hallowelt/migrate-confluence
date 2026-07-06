<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\Image;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class InterwikiImagePageLinkTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\Image::process
	 * @return void
	 */
	public function testResolvesInterwikiImagePageLinksUsingCurrentPageVersions(): void {
		$xml = '<xml xmlns:ac="some" xmlns:ri="thing">'
			. '<ac:link>'
			. '<ri:page ri:content-title="Page 1" ri:space-key="SPC1" />'
			. '<ac:link-body><ac:image><ri:attachment ri:filename="missing.png" /></ac:image></ac:link-body>'
			. '</ac:link>'
			. '<ac:link>'
			. '<ri:page ri:content-title="Page 26" ri:space-key="SPC2" />'
			. '<ac:link-body><ac:image><ri:attachment ri:filename="missing.png" /></ac:image></ac:link-body>'
			. '</ac:link>'
			. '<ac:link>'
			. '<ri:page ri:content-title="Unknown Page" ri:space-key="SPC3" />'
			. '<ac:link-body><ac:image><ri:attachment ri:filename="missing.png" /></ac:image></ac:link-body>'
			. '</ac:link>'
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new Image( $dataLookup, 1, 'Page 1', new MigrationConfig( [] ) );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( 'link=Space_1:Main_page', $actualOutput );
		$this->assertStringContainsString( 'link=wiki-space_2:Main_page', $actualOutput );
		$this->assertStringContainsString( 'link=Confluence_page---SPC3---Unknown_Page', $actualOutput );
		$this->assertStringContainsString( '[[Category:Broken_image_page_link]]', $actualOutput );
	}
}
