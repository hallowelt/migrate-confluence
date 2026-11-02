<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\EscapeWikiMarkup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;
use PHPUnit\Framework\TestCase;

class EscapeWikiMarkupTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\EscapeWikiMarkup::preprocess
	 * @dataProvider provideCases
	 * @param string $input
	 * @param string $expected
	 * @return void
	 */
	public function testPreprocess( string $input, string $expected ): void {
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$placeholderManager = new PlaceholderManager();
		( new EscapeWikiMarkup( $placeholderManager ) )->preprocess( $dom );

		$actual = $placeholderManager->replacePlaceholders( $dom->saveXML( $dom->documentElement ) );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return array
	 */
	public static function provideCases(): array {
		$ns = 'xmlns:ac="ac_ns" xmlns:ri="ri_ns"';

		return [
			'link and italic markup is wrapped' => [
				"<root><p>no [[wiki]] ''markup''</p></root>",
				"<root><p><nowiki>no [[wiki]] ''markup''</nowiki></p></root>",
			],
			'template call is wrapped, surrounding whitespace stays outside' => [
				"<root><p> a {{tpl}} b </p></root>",
				"<root><p> <nowiki>a {{tpl}} b</nowiki> </p></root>",
			],
			'magic word is wrapped' => [
				"<root><p>__NOTOC__</p></root>",
				"<root><p><nowiki>__NOTOC__</nowiki></p></root>",
			],
			'only the affected text node is wrapped' => [
				"<root><p>plain <b>[[x]]</b> plain</p></root>",
				"<root><p>plain <b><nowiki>[[x]]</nowiki></b> plain</p></root>",
			],
			'plain text is unchanged' => [
				"<root><p>nothing special, a [single] {bracket} and 'quote'</p></root>",
				"<root><p>nothing special, a [single] {bracket} and 'quote'</p></root>",
			],
			'pre and code are unchanged' => [
				"<root><pre>[[x]]</pre><code>{{y}}</code></root>",
				"<root><pre>[[x]]</pre><code>{{y}}</code></root>",
			],
			'macro parameter and plain text body are unchanged' => [
				"<root $ns><ac:structured-macro><ac:parameter>[[x]]</ac:parameter>"
					. "<ac:plain-text-body>{{y}}</ac:plain-text-body></ac:structured-macro></root>",
				"<root $ns><ac:structured-macro><ac:parameter>[[x]]</ac:parameter>"
					. "<ac:plain-text-body>{{y}}</ac:plain-text-body></ac:structured-macro></root>",
			],
			'rich text body of a macro is wrapped' => [
				"<root $ns><ac:rich-text-body><p>[[x]]</p></ac:rich-text-body></root>",
				"<root $ns><ac:rich-text-body><p><nowiki>[[x]]</nowiki></p></ac:rich-text-body></root>",
			],
		];
	}
}
