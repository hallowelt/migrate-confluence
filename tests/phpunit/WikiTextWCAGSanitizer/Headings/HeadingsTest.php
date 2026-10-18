<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\WikiTextWCAGSanitizer\Headings;

use HalloWelt\MigrateConfluence\Utility\WikiTextWcagSanitizer\Headings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\WikiTextWCAGSanitizer
 */
class HeadingsTest extends TestCase {

	public function testSanitize(): void {
		$sanitizer = new Headings();

		// Convert first heading to level 2
		$wikitext = '= Heading 1 =';
		$expected = "== Heading 1 ==\n\n[[Category:WCAG/Heading_order]]";
		$sanitized = $sanitizer->sanitize( $wikitext );
		$this->assertSame(
			$expected, $sanitized,
			'Heading 1 should be converted from level 1 to level 2'
		);

		$wikitext = '=== Heading 1 ===';
		$expected = "== Heading 1 ==\n\n[[Category:WCAG/Heading_order]]";
		$sanitized = $sanitizer->sanitize( $wikitext );
		$this->assertSame(
			$expected, $sanitized,
			'Heading 1 should be converted from level 3 to level 2'
		);

		//
		$wikitext = file_get_contents( __DIR__ . '/input.wikitext' );
		$expected = file_get_contents( __DIR__ . '/output.wikitext' );

		$sanitized = $sanitizer->sanitize( $wikitext );
		$this->assertSame(
			$expected, $sanitized,
			'The sanitized wikitext should contain headings that are adjusted to be WCAG compliant'
		);
	}
}
