<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ExtractComplexLinkContent;
use PHPUnit\Framework\TestCase;

class ExtractComplexLinkContentTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ExtractComplexLinkContent::process
	 * @dataProvider provideExtractCases
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testProcess( string $input, string $expected ): void {
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExtractComplexLinkContent();
		$processor->process( $dom );

		$this->assertEquals( $expected, $dom->saveXML( $dom->documentElement ) );
	}

	/**
	 * @return array
	 */
	public static function provideExtractCases(): array {
		$ns = 'xmlns:ac="ac_ns" xmlns:ri="ri_ns"';

		return [
			'simple text link unchanged' => [
				"<root $ns><p><a href=\"https://example.com\">Click here</a></p></root>",
				"<root $ns><p><a href=\"https://example.com\">Click here</a></p></root>",
			],
			'link with only ac:image unchanged' => [
				"<root $ns><p><a href=\"https://example.com\"><ac:image><ri:url ri:value=\"https://img.example.com/img.png\"/></ac:image></a></p></root>",
				"<root $ns><p><a href=\"https://example.com\"><ac:image><ri:url ri:value=\"https://img.example.com/img.png\"/></ac:image></a></p></root>",
			],
			'internal link unchanged' => [
				"<root $ns><p><a href=\"/wiki/SomePage\"><span>text</span></a></p></root>",
				"<root $ns><p><a href=\"/wiki/SomePage\"><span>text</span></a></p></root>",
			],
			'link with span child extracted' => [
				"<root $ns><p><a href=\"https://example.com\"><span>content</span></a></p></root>",
				"<root $ns><p>https://example.com<span>content</span></p></root>",
			],
			'link with br child extracted' => [
				"<root $ns><p><a href=\"https://example.com\"><br/></a></p></root>",
				"<root $ns><p>https://example.com<br/></p></root>",
			],
			'complex link with span+br+span extracted' => [
				"<root $ns><p><a href=\"https://example.com\"><span>A</span><br/><span>B</span></a></p></root>",
				"<root $ns><p>https://example.com<span>A</span><br/><span>B</span></p></root>",
			],
			'extracted children placed before existing next sibling' => [
				"<root $ns><p><a href=\"https://example.com\"><span>child</span></a><em>next</em></p></root>",
				"<root $ns><p>https://example.com<span>child</span><em>next</em></p></root>",
			],
			'link with nested ac:image inside span extracted' => [
				"<root $ns><p><a href=\"https://example.com\"><span><ac:image><ri:url ri:value=\"https://img.example.com/img.png\"/></ac:image></span></a></p></root>",
				"<root $ns><p>https://example.com<span><ac:image><ri:url ri:value=\"https://img.example.com/img.png\"/></ac:image></span></p></root>",
			],
		];
	}
}
