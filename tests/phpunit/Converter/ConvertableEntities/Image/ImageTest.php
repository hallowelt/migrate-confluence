<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Image;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Confluence "ac:image" objects converting to HTMl works correctly.
 *
 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image
 */
class ImageTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image::process()
	 */
	public function testProcessLinkSuccess() {
		$domInput = new DOMDocument();
		// Load input XML
		$domInput->loadXML( file_get_contents( __DIR__ . '/image_link_input.xml' ) );

		$xpath = new DOMXPath( $domInput );

		// Convert image
		$img = $domInput->getElementsByTagName( 'image' )->item( 0 );
		$imgConvert = new Image( $this->createMock( ConversionDataLookup::class ), 0, '' );
		$imgConvert->process( null, $img, $domInput, $xpath );

		// Get converted element
		/** @var DOMElement $imgActual */
		$imgActual = $domInput->getElementsByTagName( 'img' )->item( 0 );

		$domOutput = new DOMDocument();
		// Load output XML
		$domOutput->loadXML( file_get_contents( __DIR__ . '/image_link_output.xml' ) );

		/** @var DOMElement $imgExpected */
		$imgExpected = $domOutput->getElementsByTagName( 'img' )->item( 0 );

		$attributesActual = [];
		foreach ( $imgActual->attributes as $attribute ) {
			$attributesActual[$attribute->name] = $attribute->value;
		}

		$attributesExpect = [];
		foreach ( $imgExpected->attributes as $attribute ) {
			$attributesExpect[$attribute->name] = $attribute->value;
		}

		// Check that image was converted correctly, all attributes are preserved
		$this->assertXmlStringEqualsXmlString(
			$domOutput->saveXML( $imgExpected ),
			$domInput->saveXML( $imgActual )
		);

		$this->assertEquals( $attributesExpect, $attributesActual );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image::process()
	 */
	public function testProcessAttachmentSuccess() {
		/**
		 * If link has a child node ri:attachment
		 * Covers Image::makeImageLink
		 */
		$this->processAttachment(
			'image_attachment_input_1.xml',
			'image_attachment_output_1.xml',
		);

		/**
		 * If link has a child node ri:url
		 * Covers Image::makeImageTag
		 */
		$this->processAttachment(
			'image_attachment_input_2.xml',
			'image_attachment_output_2.xml',
		);
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function processAttachment( $input, $output ): void {
		$domInput = new DOMDocument();
		// Load input XML
		$domInput->loadXML( file_get_contents( __DIR__ . '/' . $input ) );

		$xpath = new DOMXPath( $domInput );

		// Convert attachment image
		$img = $domInput->getElementsByTagName( 'image' )->item( 0 );
		$mockConversionDataLookup = $this->createMock( ConversionDataLookup::class );
		$mockConversionDataLookup->method( 'getTargetFileTitleFromConfluenceFileKey' )->willReturn( 'SampleImage.png' );
		$imgConvert = new Image( $mockConversionDataLookup, 0, '' );
		$imgConvert->process( null, $img, $domInput, $xpath );

		// As far as attachment image is converted not to an image tag, but to a string
		// we should just check converted image's span as a content of parent 'div'
		$attachmentActualRaw = $domInput->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$attachmentActual = trim( $attachmentActualRaw );

		$domOutput = new DOMDocument();
		// Load output XML
		$domOutput->loadXML( file_get_contents( __DIR__ . '/' . $output ) );
		$attachmentExpectedRaw = $domOutput->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$attachmentExpected = trim( $attachmentExpectedRaw );

		$this->assertEquals( $domInput->saveXML(), $domOutput->saveXML() );
		$this->assertEquals( $attachmentExpected, $attachmentActual );
	}

}
