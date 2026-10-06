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

	public function testSanitizeAddsCategoryOnlyWhenCaptionIsMissing(): void {
		$sanitizer = new Tables();

		$wikitextWithCaption = "{| class=\"w\"\n|+ My caption\n! h\n|-\n| d\n|}";
		$sanitized = $sanitizer->sanitize( $wikitextWithCaption, "Table" );
		$this->assertStringNotContainsString(
			'[[Category:', $sanitized,
			'No maintenance category should be added when all tables already have a caption'
		);

		$wikitextWithoutCaption = "{| class=\"w\"\n! h\n|-\n| d\n|}";
		$sanitized = $sanitizer->sanitize( $wikitextWithoutCaption, "Table" );
		$this->assertStringContainsString(
			'[[Category:WCAG/Table_missing_caption]]', $sanitized,
			'A maintenance category should be added when a table is missing its caption'
		);
	}

	public function testSanitizeSingleLineTableKeepsHeaderCell(): void {
		$sanitizer = new Tables();

		$wikitext = '{| class="w"! Header1! Header2|-| d1| d2|}';
		$sanitized = $sanitizer->sanitize( $wikitext, "Table" );
		$this->assertStringContainsString(
			'! Header1', $sanitized,
			'A header cell must not be demoted to a data cell when inserting a caption'
		);
	}
}
