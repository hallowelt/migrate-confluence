<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\InvalidContentCategories;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Converter\Postprocessor\InvalidContentCategories
 */
class InvalidContentCategoriesTest extends TestCase {

	public function testNoChangeWhenReasonUnknown(): void {
		$postprocessor = new InvalidContentCategories( 'unknown reason' );
		$this->assertSame( 'some content', $postprocessor->postprocess( 'some content' ) );
	}

	public function testNoChangeWhenReasonEmpty(): void {
		$postprocessor = new InvalidContentCategories( '' );
		$this->assertSame( 'some content', $postprocessor->postprocess( 'some content' ) );
	}

	public function testOverlongPageCategory(): void {
		$postprocessor = new InvalidContentCategories( InvalidContentCategories::REASON_BODY_TOO_LONG );
		$result = $postprocessor->postprocess( 'content' );
		$this->assertStringContainsString( '[[Category:Overly_long_page]]', $result );
	}

	public function testTitleEndsWithInvalidCharacter(): void {
		$postprocessor = new InvalidContentCategories( 'Title ends with invalid character' );
		$result = $postprocessor->postprocess( 'content' );
		$this->assertStringContainsString(
			'[[Category:Invalid_title_ends_with_invalid_character]]',
			$result
		);
	}

	public function testInvalidNamespaceCharacter(): void {
		$postprocessor = new InvalidContentCategories( 'Invalid namespace character detected' );
		$result = $postprocessor->postprocess( 'content' );
		$this->assertStringContainsString( '[[Category:Invalid_title_namespace_character]]', $result );
	}

	public function testTitleMultipleColons(): void {
		$postprocessor = new InvalidContentCategories( 'Title contains multiple colons' );
		$result = $postprocessor->postprocess( 'content' );
		$this->assertStringContainsString( '[[Category:Invalid_title_multiple_colons]]', $result );
	}

	public function testTitleTooLong(): void {
		$postprocessor = new InvalidContentCategories( 'Title contains too many characters (>255)' );
		$result = $postprocessor->postprocess( 'content' );
		$this->assertStringContainsString( '[[Category:Invalid_title_too_long]]', $result );
	}

	public function testMultipleMatchingReasonsProduceMultipleCategories(): void {
		$invalidText = "Title ends with invalid character\nTitle contains multiple colons";
		$postprocessor = new InvalidContentCategories( $invalidText );
		$result = $postprocessor->postprocess( 'content' );

		$this->assertStringContainsString(
			'[[Category:Invalid_title_ends_with_invalid_character]]',
			$result
		);
		$this->assertStringContainsString( '[[Category:Invalid_title_multiple_colons]]', $result );
	}

	public function testContentIsRtrimmedBeforeCategoriesAppended(): void {
		$postprocessor = new InvalidContentCategories( 'Title contains multiple colons' );
		$result = $postprocessor->postprocess( "content   \n\n" );

		$this->assertStringStartsWith( 'content', $result );
		$this->assertStringContainsString( '[[Category:', $result );
		// No whitespace between original content and category line.
		$this->assertStringNotContainsString( "content   \n\n[[", $result );
	}
}
