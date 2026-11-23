<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\Processor\Categories;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\Hooks\HookHandler;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;

/**
 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\Categories
 */
class CategoriesTest extends TestCase {

	private string $tmpDir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = dirname( __DIR__, 4 ) . '/.tmp/categories-test-' . uniqid( '', true );
		mkdir( $this->tmpDir . '/result', 0755, true );
	}

	protected function tearDown(): void {
		HookHandler::setUp( [ 'filters' => [ 'compose-category-content' => static fn ( $c ) => $c ] ] );
		foreach ( glob( $this->tmpDir . '/result/*' ) as $file ) {
			unlink( $file );
		}
		rmdir( $this->tmpDir . '/result' );
		rmdir( $this->tmpDir );
		parent::tearDown();
	}

	/**
	 * @return array<string, string> Page title => wikitext
	 */
	private function composeCategories( array $labels ): array {
		$lookup = $this->createMock( DBComposerDataLookup::class );
		$lookup->method( 'getCategoryLabelsForSpaceIds' )->willReturn( $labels );
		$processor = new Categories(
			new Builder(), $this->createMock( Output::class ), $this->tmpDir, new MigrationConfig( [] ), $lookup
		);
		$processor->setCurrentSpaceIds( [ 1 ] );
		$processor->execute();

		$path = $this->tmpDir . '/result/categories.xml';
		if ( !is_file( $path ) ) {
			return [];
		}
		$pages = [];
		foreach ( simplexml_load_file( $path )->page as $page ) {
			$pages[(string)$page->title] = (string)$page->revision->text;
		}
		return $pages;
	}

	public function testOnePagePerSanitizedCategory(): void {
		$pages = $this->composeCategories( [ 'zebra', 'apple', 'a|b', 'a b' ] );

		$this->assertSame(
			[
				'Category:A b' => 'Pages with the label A b',
				'Category:A pipe b' => 'Pages with the label A pipe b',
				'Category:Apple' => 'Pages with the label Apple',
				'Category:Zebra' => 'Pages with the label Zebra',
			],
			$pages
		);
	}

	public function testNoLabelsWritesNoFile(): void {
		$this->assertSame( [], $this->composeCategories( [] ) );
	}

	public function testHookFiltersContent(): void {
		HookHandler::setUp( [ 'filters' => [
			'compose-category-content' => static fn ( $content, $context ) => $content . ' [' . $context['title'] . ']'
		] ] );

		$this->assertSame(
			[ 'Category:Apple' => 'Pages with the label Apple [Category:Apple]' ],
			$this->composeCategories( [ 'apple' ] )
		);
	}
}
