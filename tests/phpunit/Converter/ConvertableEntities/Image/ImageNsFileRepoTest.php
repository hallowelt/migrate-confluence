<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Image;

use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Confluence "ac:image" objects converting to HTMl works correctly.
 *
 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image
 */
class ImageNsFileRepoTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image::process()
	 */
	public function testProcessAttachmentSuccess() {
		/**
		 * If link has a child node ri:url
		 * Covers Image::makeImageTag
		 */
		$this->processAttachment(
			'image_attachment_input_1.xml',
			'image_attachment_output_3.xml',
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
		$mockConversionDataLookup->method( 'getTargetFileTitleFromConfluenceFileKey' )
			->willReturn( 'ABC_SampleImage.png' );

		$imgConvert = new Image( $mockConversionDataLookup, 0, '', true );
		$imgConvert->process( null, $img, $domInput, $xpath );

		// As far as attachment image is converted not to an image tag, but to a string
		// we should just check converted image's span as a content of parent 'div'
		$attachmentActualRaw = $domInput->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$attachmentActual = trim( $attachmentActualRaw );

		$domExpected = new DOMDocument();
		// Load output XML
		$domExpected->loadXML( file_get_contents( __DIR__ . '/' . $output ) );
		$attachmentExpectedRaw = $domExpected->getElementsByTagName( 'div' )->item( 0 )->textContent;
		$attachmentExpected = trim( $attachmentExpectedRaw );

		$this->assertEquals( $domExpected->saveXML(), $domInput->saveXML() );
		$this->assertEquals( $attachmentExpected, $attachmentActual );
	}

}
