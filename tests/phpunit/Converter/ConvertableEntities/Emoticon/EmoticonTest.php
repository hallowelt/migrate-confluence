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

		$xpath = new DOMXPath( $domInput );

		// Convert emoticons
		$emoticonConvert = new Emoticon();

		$emoticonsLive = $domInput->getElementsByTagName( 'emoticon' );
		$emoticons = [];
		foreach( $emoticonsLive as $el ) {
			$emoticons[] = $el;
		}

		foreach($emoticons as $emoticon) {
			$emoticonConvert->process(null, $emoticon, $domInput, $xpath);
		}

		// Compare string from input XML with expected XML output
		$this->assertXmlStringEqualsXmlFile( __DIR__ . '/emoticon_output.xml', $domInput);

		$domOutput = new DOMDocument();
		// Load output XML
		$domOutput->loadXML( file_get_contents( __DIR__ . '/emoticon_output.xml' ) );

		$emoticonsActual = [];

		$emoticons = $domInput->getElementsByTagName( 'p' );
		foreach( $emoticons as $emoticon ) {
			$emoticonActualRaw = $emoticon->textContent;
			$emoticonsActual[] = trim( $emoticonActualRaw );
		}

		$emoticonsExpected = [];

		$emoticons = $domOutput->getElementsByTagName( 'p' );
		foreach( $emoticons as $emoticon ) {
			$emoticonExpectedRaw = $emoticon->textContent;
			$emoticonsExpected[] = trim( $emoticonExpectedRaw );
		}

		$this->assertEquals($emoticonsExpected, $emoticonsActual);
	}

}