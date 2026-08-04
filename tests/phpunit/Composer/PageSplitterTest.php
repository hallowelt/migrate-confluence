<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer;

use HalloWelt\MigrateConfluence\Composer\PageSplitter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Composer\PageSplitter
 */
class PageSplitterTest extends TestCase {

	public function testShortTextIsNotSplit(): void {
		$text = "== Section ==\nSome content.\n";
		$parts = PageSplitter::split( $text );

		$this->assertCount( 1, $parts );
		$this->assertSame( $text, $parts[0] );
	}

	public function testSplitsAtHeadingBoundary(): void {
		// Build a text that is just over 512 KiB with a heading in the second half.
		$chunkA = str_repeat( 'a', 300000 );
		$heading = "\n== Section Two ==\n";
		$chunkB = str_repeat( 'b', 300000 );
		$text = $chunkA . $heading . $chunkB;

		$parts = PageSplitter::split( $text );

		$this->assertCount( 2, $parts );
		// The heading must start the second chunk, not be swallowed by the first.
		$this->assertStringStartsWith( '== Section Two ==', $parts[1] );
		// Reassembled text must equal the original.
		$this->assertSame( $text, implode( '', $parts ) );
	}

	public function testFallsBackToBlankLineWhenNoHeading(): void {
		$chunkA = str_repeat( 'a', 300000 );
		$gap = "\n\n";
		$chunkB = str_repeat( 'b', 300000 );
		$text = $chunkA . $gap . $chunkB;

		$parts = PageSplitter::split( $text );

		$this->assertCount( 2, $parts );
		$this->assertSame( $text, implode( '', $parts ) );
	}

	public function testHardSplitRespectsUtf8Boundaries(): void {
		// Build a text slightly over 512 KiB with no headings or blank lines,
		// ending in a multi-byte UTF-8 sequence that straddles the chunk boundary.
		// U+00E9 (é) encodes as 0xC3 0xA9 (2 bytes).
		// one byte before 512 KiB
		$filler = str_repeat( 'a', 524287 );
		// é + tail
		$text = $filler . "\xC3\xA9" . str_repeat( 'b', 100 );

		$parts = PageSplitter::split( $text );

		// Every chunk must be valid UTF-8.
		foreach ( $parts as $i => $part ) {
			$this->assertTrue(
				mb_check_encoding( $part, 'UTF-8' ),
				"Part $i is not valid UTF-8"
			);
		}
		$this->assertSame( $text, implode( '', $parts ) );
	}

	public function testBuildPartTitles(): void {
		$titles = PageSplitter::buildPartTitles( 'NS:MyPage', 3 );

		$this->assertSame( [ 'NS:MyPage', 'NS:MyPage/2', 'NS:MyPage/3' ], $titles );
	}

	public function testAddNavigationFirstPart(): void {
		$titles = [ 'Page', 'Page/2', 'Page/3' ];
		$result = PageSplitter::addNavigation( 'content', 0, $titles );

		$this->assertStringNotContainsString( '←', $result );
		$this->assertStringContainsString( '[[Page/2|Part 2]] →', $result );
		$this->assertStringContainsString( '[[Category:Overly_long_page]]', $result );
	}

	public function testAddNavigationMiddlePart(): void {
		$titles = [ 'Page', 'Page/2', 'Page/3' ];
		$result = PageSplitter::addNavigation( 'content', 1, $titles );

		$this->assertStringContainsString( '← [[Page|Page]]', $result );
		$this->assertStringContainsString( '[[Page/3|Part 3]] →', $result );
	}

	public function testAddNavigationLastPart(): void {
		$titles = [ 'Page', 'Page/2', 'Page/3' ];
		$result = PageSplitter::addNavigation( 'content', 2, $titles );

		$this->assertStringContainsString( '←', $result );
		$this->assertStringNotContainsString( '→', $result );
		$this->assertStringContainsString( '[[Category:Overly_long_page]]', $result );
	}
}
