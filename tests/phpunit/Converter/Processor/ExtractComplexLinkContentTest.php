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
		$imgUrl = 'https://img.example.com/img.png';
		$extUrl = 'https://example.com';
		$img = "<ac:image><ri:url ri:value=\"$imgUrl\"/></ac:image>";

		return [
			'simple text link unchanged' => [
				"<root $ns><p><a href=\"$extUrl\">Click here</a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">Click here</a></p></root>",
			],
			'link with only ac:image unchanged' => [
				"<root $ns><p><a href=\"$extUrl\">$img</a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">$img</a></p></root>",
			],
			'internal link unchanged' => [
				"<root $ns><p><a href=\"/wiki/SomePage\"><span>text</span></a></p></root>",
				"<root $ns><p><a href=\"/wiki/SomePage\"><span>text</span></a></p></root>",
			],
			'link with span child unchanged' => [
				"<root $ns><p><a href=\"$extUrl\"><span>content</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\"><span>content</span></a></p></root>",
			],
			'link with br child' => [
				"<root $ns><p><a href=\"$extUrl\"><br/></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\"/></p></root>",
			],
			'complex link with span+br+span' => [
				"<root $ns><p><a href=\"$extUrl\"><span>A</span><br/><span>B</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\"><span>A</span><span>B</span></a></p></root>",
			],
			'children placed before existing next sibling unchanged' => [
				"<root $ns><p><a href=\"$extUrl\"><span>child</span></a><em>next</em></p></root>",
				"<root $ns><p><a href=\"$extUrl\"><span>child</span></a><em>next</em></p></root>",
			],
			'link with nested ac:image inside span extracted' => [
				"<root $ns><p><a href=\"$extUrl\"><span>$img</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">$img</a><span/></p></root>",
			],
			'link with nested ac:image along content extracted' => [
				"<root $ns><p><a href=\"$extUrl\">$img<span>content</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">$img</a><span>content</span></p></root>",
			],
			'link with nested ac:image inside span along content extracted' => [
				"<root $ns><p><a href=\"$extUrl\"><span>$img</span><span>content</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">$img</a><span/><span>content</span></p></root>",
			],
			'link with content with nested ac:image inside span along content extracted' => [
				"<root $ns><p><a href=\"$extUrl\"><span>content$img</span><span>content</span></a></p></root>",
				"<root $ns><p><a href=\"$extUrl\">$img</a><span>content</span><span>content</span></p></root>",
			],
		];
	}
}
