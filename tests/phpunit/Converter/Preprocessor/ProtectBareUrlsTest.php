<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\ProtectBareUrls;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;
use PHPUnit\Framework\TestCase;

class ProtectBareUrlsTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM\ProtectBareUrls::preprocess
	 * @dataProvider provideCases
	 * @param string $input
	 * @param string $expected DOM with placeholders
	 * @param string $restored DOM after placeholders are replaced
	 * @return void
	 */
	public function testPreprocess( string $input, string $expected, string $restored ): void {
		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$placeholderManager = new PlaceholderManager();
		( new ProtectBareUrls( $placeholderManager ) )->preprocess( $dom );

		$actual = $dom->saveXML( $dom->documentElement );
		$this->assertEquals( $expected, $actual );
		$this->assertEquals( $restored, $placeholderManager->replacePlaceholders( $actual ) );
	}

	/**
	 * @return array
	 */
	public static function provideCases(): array {
		$ns = 'xmlns:ac="ac_ns"';

		return [
			'url in text' => [
				"<root><p>see https://a.b/c?d=1&amp;e=2 now</p></root>",
				"<root><p>see convert:placeholder=0 now</p></root>",
				"<root><p>see https://a.b/c?d=1&amp;e=2 now</p></root>",
			],
			'trailing punctuation and parenthesis stay outside' => [
				"<root><p>(https://a.b/c), https://a.b/d.</p></root>",
				"<root><p>(convert:placeholder=0)," . " convert:placeholder=1.</p></root>",
				"<root><p>(https://a.b/c), https://a.b/d.</p></root>",
			],
			'balanced parenthesis stays in the url' => [
				"<root><p>https://a.b/Foo_(bar)</p></root>",
				"<root><p>convert:placeholder=0</p></root>",
				"<root><p>https://a.b/Foo_(bar)</p></root>",
			],
			'links, code, pre and macro parameters are unchanged' => [
				"<root $ns><a href=\"https://a.b\">https://a.b</a><code>https://a.b</code><pre>https://a.b</pre>"
					. "<ac:parameter>https://a.b</ac:parameter></root>",
				"<root $ns><a href=\"https://a.b\">https://a.b</a><code>https://a.b</code><pre>https://a.b</pre>"
					. "<ac:parameter>https://a.b</ac:parameter></root>",
				"<root $ns><a href=\"https://a.b\">https://a.b</a><code>https://a.b</code><pre>https://a.b</pre>"
					. "<ac:parameter>https://a.b</ac:parameter></root>",
			],
		];
	}
}
