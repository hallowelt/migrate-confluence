<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\MacroColumn;
use PHPUnit\Framework\TestCase;

class MacroColumnTest extends TestCase {

	private $input = <<<HERE
	<ac:structured-macro ac:name="column" ac:schema-version="1" ac:macro-id="someID"><ac:parameter ac:name="width">33%</ac:parameter><ac:rich-text-body><h3 style="text-align: center;">Lorem ipsum</h3><p class="auto-cursor-target"><ac:image ac:align="center" ac:thumbnail="true" ac:width="240"><ri:attachment ri:filename="someImage.png"><ri:content-entity ri:content-id="someID" /></ri:attachment></ac:image></p><ac:structured-macro ac:name="note" ac:schema-version="1" ac:macro-id="someID"><ac:rich-text-body><h3 style="text-align: center;">Lorem ipsum dolor</h3></ac:rich-text-body></ac:structured-macro><p class="auto-cursor-target"><br /></p></ac:rich-text-body></ac:structured-macro>
HERE;
	private $expectedOutput = <<<HERE
	<div class="structured-macro-column"><ac:parameter ac:name="width">33%</ac:parameter><ac:rich-text-body><h3 style="text-align: center;">Lorem ipsum</h3><p class="auto-cursor-target"><ac:image ac:align="center" ac:thumbnail="true" ac:width="240"><ri:attachment ri:filename="someImage.png"><ri:content-entity ri:content-id="someID" /></ri:attachment></ac:image></p><ac:structured-macro ac:name="note" ac:schema-version="1" ac:macro-id="someID"><ac:rich-text-body><h3 style="text-align: center;">Lorem ipsum dolor</h3></ac:rich-text-body></ac:structured-macro><p class="auto-cursor-target"><br /></p></ac:rich-text-body></div>
HERE;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\CDATAClosingFixer::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$testDataDir = dirname( __DIR__ ) . '/../data';
		$input = file_get_contents( "$testDataDir/preservetableattributestest-input.xml" );
		$expectedOutput = file_get_contents( "$testDataDir/preservetableattributestest-output.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new MacroColumn();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML();

		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
	}
}
