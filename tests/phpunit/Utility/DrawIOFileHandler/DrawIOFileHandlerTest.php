<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\DrawIOFileHandler;

use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler
 */
class DrawIOFileHandlerTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler::isDrawIODataFile
	 */
	public function testIsDrawIODataFile() {
		$drawIoFileHandler = new DrawIOFileHandler();

		$this->assertTrue( $drawIoFileHandler->isDrawIODataFile( 'diagram.drawio' ) );
		$this->assertTrue( $drawIoFileHandler->isDrawIODataFile( 'diagram.drawio.tmp' ) );

		$this->assertFalse( $drawIoFileHandler->isDrawIODataFile( 'diagram.drawio.png' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler::isDrawIODataContent
	 */
	public function testIsDrawIODataContent() {
		$drawIoFileHandler = new DrawIOFileHandler();

		$diagramXml = <<<HERE
<mxfile host="ac.draw.io" modified="2023-08-09T08:35:34.679Z">
	<somestring>Some content</somestring>
	<diagram>Some diagram content...</diagram>
</mxfile>
HERE;

		$diagramXml2 = file_get_contents( __DIR__ . '/data/diagram.drawio' );

		$notDiagramXmlString1 = <<<HERE
<mxfile host="ac.draw.io" modified="2023-08-09T08:35:34.679Z">
	<somestring>Some content</somestring>
	<notdiagram>Not a diagram</notdiagram>
</mxfile>
HERE;

		$notDiagramXmlString2 = <<<HERE
<?xml version="1.0" encoding="utf8" ?>
	<somestring>Some content</somestring>
	<diagram>Not a diagram</diagram>
</xml>
HERE;

		$this->assertTrue( $drawIoFileHandler->isDrawIODataContent( $diagramXml ) );
		$this->assertTrue( $drawIoFileHandler->isDrawIODataContent( $diagramXml2 ) );

		$this->assertFalse( $drawIoFileHandler->isDrawIODataContent( $notDiagramXmlString1 ) );
		$this->assertFalse( $drawIoFileHandler->isDrawIODataContent( $notDiagramXmlString2 ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler::isDrawIOImage
	 */
	public function testIsDrawIOImage() {
		$drawIoFileHandler = new DrawIOFileHandler();

		$this->assertFalse( $drawIoFileHandler->isDrawIOImage( 'diagram.drawio' ) );
		$this->assertFalse( $drawIoFileHandler->isDrawIOImage( 'diagram.drawio.tmp' ) );

		$this->assertTrue( $drawIoFileHandler->isDrawIOImage( 'diagram.drawio.png' ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler::bakeDiagramDataIntoImage
	 */
	public function testBakeDiagramDataIntoImage() {
		$drawIoFileHandler = new DrawIOFileHandler();

		$diagramXml = file_get_contents( __DIR__ . '/data/diagram.drawio' );
		$imageContent = file_get_contents( __DIR__ . '/data/diagram.drawio.png' );

		// Get expected diagram XML
		$matches = [];
		preg_match( '#<mxfile.*?>(.*?)</mxfile>#s', $diagramXml, $matches );

		$expectedDiagramXML = trim( $matches[0] );

		// Bake diagram XML into PNG image meta data
		$imageContent = $drawIoFileHandler->bakeDiagramDataIntoImage( $imageContent, $diagramXml );

		// Extract and check diagram XML from the PNG
		// Extraction is done with the same algorithm how it is done in the wiki
		$encodedXML = preg_replace(
			'#^.*?tEXt(.*?)IDAT.*?$#s',
			'$1',
			$imageContent
		);
		$encodedXML = preg_replace( '/[[:^print:]]/', '', $encodedXML );
		$partiallyDecodedXML = urldecode( $encodedXML );

		// Get actual diagram XML after extraction from PNG
		$matches = [];
		preg_match( '#<mxfile.*?>(.*?)</mxfile>#s', $partiallyDecodedXML, $matches );

		$actualDiagramXML = trim( $matches[0] );

		$this->assertEquals( $expectedDiagramXML, $actualDiagramXML );
	}
}
