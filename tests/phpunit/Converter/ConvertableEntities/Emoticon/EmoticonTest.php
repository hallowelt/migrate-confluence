<?php


namespace HalloWelt\MigrateConfluence\Tests\Converter\ConvertableEntities\Emoticon;


use DOMDocument;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Emoticon;
use PHPUnit\Framework\TestCase;

class EmoticonTest extends TestCase
{
    /**
     * @covers \HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Emoticon::process()
     */
    public function testProcessEmoticonSuccess()
    {
        $domInput = new DOMDocument();
        // Load input XML
        $domInput->loadXML( file_get_contents( __DIR__ . '/emoticon_input.xml' ) );

        $xpath = new DOMXPath($domInput);

        // Convert emoticon
        $emoticon = $domInput->getElementsByTagName( 'emoticon' )->item(0);
        $emoticonConvert = new Emoticon();
        $emoticonConvert->process(null, $emoticon, $domInput, $xpath);

        // Compare string from input XML with expected XML output
        $this->assertXmlStringEqualsXmlFile( __DIR__ . '/emoticon_output.xml', $domInput->saveXML());
    }

}