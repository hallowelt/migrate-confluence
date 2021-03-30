<?php


namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Link;


use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Confluence "ac:link" objects converting to HTML works correctly.
 *
 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link
 */
class LinkTest extends TestCase
{
	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link::process()
	 */
	public function testProcessAttachmentLinkSuccess()
	{
		$domInput = new DOMDocument();
		// Load input XML
		$domInput->loadXML( file_get_contents( __DIR__ . '/link_attachment_input.xml' ) );

		$xpath = new DOMXPath( $domInput );

		$linksLive = $domInput->getElementsByTagName(  'link' );

		$links = [];
		foreach( $linksLive as $linkLive ) {
			$links[] = $linkLive;
		}

		$linkConvert = new Link();

		// Convert links
		foreach( $links as $link ) {
			$linkConvert->process( null, $link, $domInput, $xpath );
		}

		$this->assertXmlStringEqualsXmlFile( __DIR__ . '/link_attachment_output.xml', $domInput );

		$linksActual = [];

		$linksActualLive = $domInput->getElementsByTagName('div');
		foreach( $linksActualLive as $link ) {
			$linkActualRaw = $link->textContent;
			$linksActual[] = trim( $linkActualRaw );
		}

		$domOutput = new DOMDocument();
		// Load output XML
		$domOutput->loadXML( file_get_contents( __DIR__ . '/link_attachment_output.xml' ) );

		$linksExpected = [];

		$linksExpectedLive = $domOutput->getElementsByTagName('div');
		foreach( $linksExpectedLive as $link ) {
			$linkExpectedRaw = $link->textContent;
			$linksExpected[] = trim( $linkExpectedRaw );
		}

		$this->assertEquals( $linksExpected, $linksActual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Link::process()
	 */
	public function testProcessPageLinkSuccess()
	{
		$domInput = new DOMDocument();
		// Load input XML
		$domInput->loadXML( file_get_contents( __DIR__ . '/link_page_input.xml' ) );

		$xpath = new DOMXPath( $domInput );

		// Convert link
		$link = $domInput->getElementsByTagName(  'link' )->item( 0 );
		$linkConvert = new Link();
		$linkConvert->process( null, $link, $domInput, $xpath );

		$linkActualRaw = $domInput->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$linkActual = trim( $linkActualRaw );

		$domOutput = new DOMDocument();
		// Load output XML
		$domOutput->loadXML( file_get_contents( __DIR__ . '/link_page_output.xml' ) );

		// As far as attachment link is converted not to an link tag, but to a string
		// we should just check converted link's span as a content of parent 'div'
		$linkExpectedRaw = $domOutput->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$linkExpected = trim( $linkExpectedRaw );

		$this->assertEquals( $linkExpected, $linkActual );
	}

	public function testProcessPageAndNamespaceLinkSuccess()
	{

	}
}