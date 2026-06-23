<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter;

use DOMDocument;
use HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\FlattenListItemWithNoStyle;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * Tests that HTML list elements (<ul>, <ol>, <li>) are correctly converted to
 * MediaWiki wikitext via Pandoc. Covers both simple and nested list cases.
 */
class ListConversionTest extends TestCase {

	/** @var string */
	private string $tempDir;

	/** @var PandocHTML */
	private PandocHTML $converter;

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/list-conversion-test-' . uniqid();
		mkdir( $this->tempDir, 0755, true );

		$workspace = new Workspace( new SplFileInfo( $this->tempDir ) );
		$this->converter = new PandocHTML( [], $workspace );
	}

	protected function tearDown(): void {
		$this->removeDirectory( $this->tempDir );
	}

	/**
	 * @covers \HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML::doConvert
	 */
	public function testSimpleUnorderedList(): void {
		$inputFile = __DIR__ . '/../data/list-simple-ul-input.html';
		$result = $this->converter->convert( new SplFileInfo( $inputFile ) );

		$this->assertSame(
			"* Item 1\n* Item 2\n* Item 3",
			trim( $result )
		);
	}

	/**
	 * @covers \HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML::doConvert
	 */
	public function testSimpleOrderedList(): void {
		$inputFile = __DIR__ . '/../data/list-simple-ol-input.html';
		$result = $this->converter->convert( new SplFileInfo( $inputFile ) );

		$this->assertSame(
			"# First\n# Second\n# Third",
			trim( $result )
		);
	}

	/**
	 * @covers \HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML::doConvert
	 */
	public function testNestedUnorderedList(): void {
		$inputFile = __DIR__ . '/../data/list-nested-ul-input.html';
		$result = $this->converter->convert( new SplFileInfo( $inputFile ) );

		$this->assertSame(
			"* Parent\n** Child 1\n** Child 2",
			trim( $result )
		);
	}

	/**
	 * The outer <li style="list-style-type: none;"> is an invisible Confluence
	 * wrapper. After FlattenListItemWithNoStyle runs before Pandoc, the inner
	 * items are promoted to level-1 bullets with no "* **" bold-markup issue.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\FlattenListItemWithNoStyle::preprocess
	 * @covers \HalloWelt\MediaWiki\Lib\Migration\Converter\PandocHTML::doConvert
	 */
	public function testNestedListWithListStyleTypeNone(): void {
		$inputFile = __DIR__ . '/../data/list-nested-none-style-input.html';

		$dom = new DOMDocument();
		$dom->loadHTMLFile( $inputFile );

		$preprocessor = new FlattenListItemWithNoStyle();
		$preprocessor->preprocess( $dom );

		$tmpFile = tempnam( $this->tempDir, 'list-none-' ) . '.html';
		$dom->saveHTMLFile( $tmpFile );

		$result = $this->converter->convert( new SplFileInfo( $tmpFile ) );

		$this->assertSame(
			"* Nested List Item 1\n* Nested List Item 2",
			trim( $result )
		);
	}

	private function removeDirectory( string $dir ): void {
		if ( !is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			if ( $item->isDir() ) {
				rmdir( $item->getRealPath() );
			} else {
				unlink( $item->getRealPath() );
			}
		}
		rmdir( $dir );
	}
}
