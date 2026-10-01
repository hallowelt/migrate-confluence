<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\WikiTextWCAGSanitizer\Headings;

use HalloWelt\MigrateConfluence\Utility\WikiTextWcagSanitizer\Tables;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Utility\WikiTextWCAGSanitizer
 */
class TablesTest extends TestCase {

	public function testSanitize(): void {
		$sanitizer = new Tables();

		$wikitext = file_get_contents( __DIR__ . '/input.wikitext' );
		$expected = file_get_contents( __DIR__ . '/output.wikitext' );

		$sanitized = $sanitizer->sanitize( $wikitext );
		$this->assertSame(
			$expected, $sanitized,
			'The sanitized wikitext should contain headings that are adjusted to be WCAG compliant'
		);
	}
}
