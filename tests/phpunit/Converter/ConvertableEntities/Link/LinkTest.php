<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Link;

use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Confluence "ac:link" objects converting to HTML works correctly.
 *
 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link
 */
class LinkTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link::process()
	 */
	public function testProcessAttachmentLinkSuccess() {
		$domInput = new DOMDocument();
		$domInput->loadXML( file_get_contents( __DIR__ . '/link_attachment_input.xml' ) );

		$xpath = new DOMXPath( $domInput );
		$linksLive = $domInput->getElementsByTagName( 'link' );

		$links = [];
		foreach ( $linksLive as $linkLive ) {
			$links[] = $linkLive;
		}

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataLookup = new ConversionDataLookup(
			[
				42 => '',
				23 => 'DEVOPS'
			],
			[],
			[
				'42---SomePage---SomeImage.png' => 'SomePage_SomeImage.png',
				'42---SomePage---SomeImage1.png' => 'SomePage_SomeImage1.png',
				'23---SomePage---SomeImage1.png' => 'DEVOPS_SomePage_SomeImage1.png'
			]
		);
		$linkConvert = new Link( $dataLookup, $currentSpaceId, $currentRawPagename );

		foreach ( $links as $link ) {
			$linkConvert->process( null, $link, $domInput, $xpath );
		}

		$linksActual = [];
		$linksActualLive = $domInput->getElementsByTagName( 'div' );
		foreach ( $linksActualLive as $link ) {
			$linkActualRaw = $link->textContent;
			$linksActual[] = trim( $linkActualRaw );
		}

		$domOutput = new DOMDocument();
		$domOutput->loadXML( file_get_contents( __DIR__ . '/link_attachment_output.xml' ) );

		$this->assertEquals( $domOutput->saveXML(), $domInput->saveXML() );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link::process()
	 */
	public function testProcessPageLinkSuccess() {
		$domInput = new DOMDocument();
		$domInput->loadXML( file_get_contents( __DIR__ . '/link_page_input.xml' ) );
		$xpath = new DOMXPath( $domInput );

		$linksLive = $domInput->getElementsByTagName( 'link' );
		$links = [];
		foreach ( $linksLive as $linkLive ) {
			$links[] = $linkLive;
		}

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataLookup = new ConversionDataLookup(
			[
				42 => '',
				23 => 'DEVOPS'
			],
			[
				'42---Page Title' => 'Page_Title',
				'42---Page Title2' => 'Page_Title2',
				'42---Page Title3' => 'Page_Title3',
				'23---Page Title3' => 'DEVOPS:Page_Title3',
			],
			[]
		);
		$linkConvert = new Link( $dataLookup, $currentSpaceId, $currentRawPagename );
		foreach ( $links as $link ) {
			$linkConvert->process( null, $link, $domInput, $xpath );
		}

		$expectedDom = new DOMDocument();
		$expectedDom->load( __DIR__ . '/link_page_output.xml' );

		$this->assertEquals( $expectedDom->saveXML(), $domInput->saveXML() );
	}
}
