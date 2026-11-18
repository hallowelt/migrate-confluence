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
			'leading colon' => [ ':x', 'X' ],
			'leading colons' => [ '::x', 'X' ],
			'percent hex' => [ '100%25', '100 25' ],
			'percent non-hex' => [ '50%off', '50%off' ],
			'tildes' => [ 'a~~~b', 'A b' ],
			'two tildes' => [ 'a~~b', 'A~~b' ],
			'single dot' => [ '.', '' ],
			'double dot' => [ '..', '' ],
			'dots in name' => [ '.a.', '.a.' ],
			'max length' => [ str_repeat( 'a', 300 ), 'A' . str_repeat( 'a', 254 ) ],
			'max length multibyte' => [ str_repeat( 'ä', 200 ), 'Ä' . str_repeat( 'ä', 126 ) ],
			'invalid utf-8' => [ "a\xFFb", 'A?b' ],
			'empty' => [ '  ', '' ],
		];
	}
}
