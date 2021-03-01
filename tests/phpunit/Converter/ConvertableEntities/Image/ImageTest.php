<?php


namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Image;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image;
use PHPUnit\Framework\TestCase;

/**
 * Tests that Confluence "ac:image" objects converting to HTMl works correctly.
 */
class ImageTest extends TestCase
{
    /**
     * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image::process()
     */
    public function testProcessLinkSuccess()
    {
        $domInput = new DOMDocument();
        // Load input XML
        $domInput->loadXML( file_get_contents( __DIR__ . '/image_link_input.xml' ) );

        $xpath = new DOMXPath($domInput);

        // Convert image
        $img = $domInput->getElementsByTagName( 'image' )->item(0);
        $imgConvert = new Image($img);
        $imgConvert->process(null, $img, $domInput, $xpath);

        // Get converted element
        /** @var DOMElement $imgActual */
        $imgActual = $domInput->getElementsByTagName( 'img' )->item(0);

        $domOutput = new DOMDocument();
        // Load output XML
        $domOutput->loadXML( file_get_contents( __DIR__ . '/image_link_output.xml' ) );

        /** @var DOMElement $imgExpected */
        $imgExpected = $domOutput->getElementsByTagName('img')->item(0);

        $attributesActual = [];
        foreach($imgActual->attributes as $attribute) {
            $attributesActual[$attribute->name] = $attribute->value;
        }

        $attributesExpect = [];
        foreach($imgExpected->attributes as $attribute) {
            $attributesExpect[$attribute->name] = $attribute->value;
        }

        // Check that image was converted correctly, all attributes are preserved
        $this->assertEqualXMLStructure($imgExpected, $imgActual, true);

        $this->assertEquals($attributesExpect, $attributesActual);
    }

    /**
     * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Image::process()
     */
    public function testProcessAttachmentSuccess()
    {
        $domInput = new DOMDocument();
        // Load input XML
        $domInput->loadXML( file_get_contents( __DIR__ . '/image_attachment_input.xml' ) );

        $xpath = new DOMXPath($domInput);

        // Convert attachment image
        $img = $domInput->getElementsByTagName( 'image' )->item(0);
        $imgConvert = new Image($img);
        $imgConvert->process(null, $img, $domInput, $xpath);

        // As far as attachment image is converted not to an image tag, but to a string
        // we should just check converted image's span as a content of parent 'div'
        $attachmentActualRaw = $domInput->getElementsByTagName( 'div' )->item(0)->textContent;
        $attachmentActual = trim($attachmentActualRaw);

        $domOutput = new DOMDocument();
        // Load output XML
        $domOutput->loadXML( file_get_contents( __DIR__ . '/image_attachment_output.xml' ) );
        $attachmentExpectedRaw = $domOutput->getElementsByTagName('div')->item(0)->textContent;
        $attachmentExpected = trim($attachmentExpectedRaw);

        $this->assertEquals($attachmentExpected, $attachmentActual);
    }

}