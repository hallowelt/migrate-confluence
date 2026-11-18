<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\Sanitizer;

use HalloWelt\MigrateConfluence\Utility\Sanitizer;
use PHPUnit\Framework\TestCase;

class SanitizerTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\Sanitizer::sanitizeCategory
	 * @dataProvider provideLabels
	 */
	public function testSanitizeCategory( string $label, string $expected ): void {
		$this->assertSame( $expected, Sanitizer::sanitizeCategory( $label ) );
	}

	public static function provideLabels(): array {
		return [
			'plain' => [ 'simple', 'Simple' ],
			'spaces' => [ 'two words', 'Two words' ],
			'umlauts' => [ 'über Äpfel', 'Über Äpfel' ],
			'ampersand' => [ 'R&D & more', 'R&D & more' ],
			'quotes' => [ "say \"hi\" 'there'", "Say \"hi\" 'there'" ],
			'colon' => [ 'colon:sep', 'Colon:sep' ],
			'slash' => [ 'slash/sub', 'Slash/sub' ],
			'pipe' => [ 'pipe|bar', 'Pipe bar' ],
			'hash' => [ 'hash#tag', 'Hash tag' ],
			'html' => [ '<b>tag</b>', 'B tag /b' ],
			'brackets' => [ '[brackets]', 'Brackets' ],
			'braces' => [ '{{braces}}', 'Braces' ],
			'only illegal' => [ '#|[]', '' ],
			'empty' => [ '  ', '' ],
		];
	}
}
