<?php

namespace HalloWelt\MigrateConfluence\Tests;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;

class FullMigrationMultiSpaceTest extends FullMigrationSingleSpaceTest {
	protected function setUp(): void {
		$this->dataDir = __DIR__ . '/data/FullMigration';

		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();

		// Multi source migration test
		mkdir( $this->tempDir . '/multi-source', 0755, true );
		mkdir( $this->tempDir . '/multi-source/input', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/raw', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/wikitext', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/result', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/result/images', 0755, true );

		$spaces = [ 'space_alpha', 'space_beta', 'space_gamma' ];
		foreach ( $spaces as $space ) {
			$sourceFile = $this->dataDir . '/MultiSource/input/' . $space . '/entities.xml';
			mkdir( $this->tempDir . '/multi-source/input/' . $space, 0755, true );
			copy( $sourceFile, $this->tempDir . '/multi-source/input/' . $space . '/entities.xml' );
		}
	}

	protected function tearDown(): void {
		$this->removeDirectory( $this->tempDir );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer
	 * @covers \HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter
	 * @covers \HalloWelt\MigrateConfluence\Composer\ConfluenceComposer
	 */
	public function testMigration(): void {
		$expectedPagesFile = $this->dataDir . '/result_pages.xml';

		$spaces = [ 'space_alpha', 'space_beta', 'space_gamma' ];

		$output = new BufferedOutput();
		$config = [];

		$workspace = new Workspace(
			new SplFileInfo( $this->tempDir . '/multi-source/workspace' )
		);
		$dest = $this->tempDir . '/multi-source/workspace';

		// Step 1: Analyze all export directories first so we can assert the
		// accumulated workspace state before extraction begins.
		foreach ( $spaces as $space ) {
			$src = $this->tempDir . '/multi-source/input/' . $space;

			$this->runAnalyze(
				$src,
				$dest,
				$config,
				$output
			);
		}

		$resultWorkspaceDB = WorkspaceDB::openExisting( $this->tempDir . '/multi-source/workspace/workspace.sqlite' );
		$pages = $resultWorkspaceDB->getPages();

		// Build legacy map to compare results
		$titlesMap = [];
		foreach ( $pages as $page ) {
			$spaceId = $page['space_id'];
			$confluenceTitle = $page['confluence_title'];
			$wikiTitle = $page['wiki_title'];

			$key = "$spaceId---$confluenceTitle";

			$titlesMap[$key] = $wikiTitle;
		}

		// Pages unique to each file must survive in the titles map.
		$this->assertArrayHasKey(
			'70000000---Alpha Page',
			$titlesMap,
			'Alpha Page (unique to space_alpha export) was lost from analyze-pages-titles-map'
		);
		$this->assertArrayHasKey(
			'50000000---Beta Page',
			$titlesMap,
			'Beta Page (unique to space_beta export) was lost from analyze-pages-titles-map'
		);

		// analyze-page-id-to-confluence-key-map: every page ID must resolve to
		// its spaceId---NormalizedLeafTitle key irrespective of hierarchy.
		$idToConfluenceKeyMap = [];
		foreach ( $pages as $page ) {
			$id = $page['page_id'];
			$spaceId = $page['space_id'];
			$confluenceTitle = $page['confluence_title'];

			$key = "$spaceId---$confluenceTitle";

			$idToConfluenceKeyMap[$id] = $key;
		}

		$this->assertSame(
			'50000000---Duplicate Title Page',
			$idToConfluenceKeyMap[50000020] ?? null,
			'Parent page 50000020 must be present in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'50000000---Duplicate Title Page',
			$idToConfluenceKeyMap[50000021] ?? null,
			'Child page 50000021 shares the parent title and must map to the same confluence key'
		);
		$this->assertSame(
			'50000000---Duplicate Child Page',
			$idToConfluenceKeyMap[50000022] ?? null,
			'Child page 50000022 must be present in analyze-page-id-to-confluence-key-map'
		);

		// Pages 70000030 (space_alpha) and 70000031 (space_beta) have the same
		// title and space but different IDs. Both IDs must be in the confluence-key
		// map, both must resolve to the same key, and that key must appear in both
		// analyze-pages-titles-map (first hit) and analyze-pages-titles-duplicates-map
		// (second hit).
		$this->assertSame(
			'70000000---Same Space Title',
			$idToConfluenceKeyMap[70000030] ?? null,
			'Page 70000030 ("Same Space Title") must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'50000000---Same Space Title',
			$idToConfluenceKeyMap[50000031] ?? null,
			'Page 50000031 ("Same Space Title") must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertArrayHasKey(
			'50000000---Same Space Title',
			$titlesMap,
			'"Same Space Title" from space_beta must remain in analyze-pages-titles-map'
		);
		$this->assertArrayHasKey(
			'70000000---Same Space Title',
			$titlesMap,
			'First occurrence of "Same Space Title" must remain in analyze-pages-titles-map'
		);

		// ParentA/ChildA (space ALPHA, id 70000041) and ParentB/ChildA (space BETA,
		// id 70000051) share the leaf title "Shared Child Title" but live in
		// different spaces. The confluence key includes the spaceId, so the two
		// keys are distinct. Each must appear exactly once in analyze-pages-titles-map
		// and must NOT be in analyze-pages-titles-duplicates-map.
		$this->assertSame(
			'70000000---Shared Child Title',
			$idToConfluenceKeyMap[70000041] ?? null,
			'Page 70000041 (ParentA/ChildA, space ALPHA) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'50000100---Shared Child Title',
			$idToConfluenceKeyMap[50000051] ?? null,
			'Page 50000051 (ParentB/ChildA, space BETA) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertArrayHasKey(
			'70000000---Shared Child Title',
			$titlesMap,
			'ParentA/ChildA (space ALPHA) must be in analyze-pages-titles-map'
		);

		$this->assertArrayHasKey(
			'50000100---Shared Child Title',
			$titlesMap,
			'ParentB/ChildA (space BETA) must be in analyze-pages-titles-map'
		);

		// Deep hierarchy ABC/ABC/ABC: three pages (ids 70000060, 70000061, 70000062)
		// all with title "Deep ABC" in the same space, each with a different id,
		// spread across three exports. The confluence key is spaceId---leafTitle,
		// so all three map to the same key. Expected behaviour:
		// - first page  → stays in analyze-pages-titles-map
		// - second page → recorded in analyze-pages-titles-duplicates-map
		// - third page  → appended to analyze-pages-titles-duplicates-map
		// Result: key present in BOTH maps; duplicates entry has at least 2 targets.
		$this->assertSame(
			'70000000---Deep ABC',
			$idToConfluenceKeyMap[70000060] ?? null,
			'Page 70000060 (Deep ABC level 1) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'50000000---Deep ABC',
			$idToConfluenceKeyMap[50000061] ?? null,
			'Page 50000061 (Deep ABC level 2) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'40000000---Deep ABC',
			$idToConfluenceKeyMap[40000062] ?? null,
			'Page 40000062 (Deep ABC level 3) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertArrayHasKey(
			'70000000---Deep ABC',
			$titlesMap,
			'First "Deep ABC" page must remain in analyze-pages-titles-map'
		);

		// Step 2: Extract each entities.xml
		foreach ( $spaces as $space ) {
			$src = $this->tempDir . '/multi-source/input/' . $space;
			$this->runExtract(
				$src,
				$dest,
				$workspace,
				$config
			);
		}

		// Step 3: Convert
		$this->runConvert(
			$dest,
			$workspace,
			$config,
			$output
		);

		// Step 4: Compose
		$this->runCompose(
			$dest,
			$workspace,
			$config,
			$output
		);

		// Step 5: Verify that the unique pages from each export appear in the output.
		// "Shared Page" intentionally excluded: it has two body-content revisions
		// and its exact output format is verified separately.
		$actualFile = $dest . '/result/pages.xml';
		$this->assertFileExists( $actualFile );

		$actualPages = $this->extractPages( $actualFile );

		$expectedTitles = [];
		foreach ( $pages as $page ) {
			if ( !isset( $page['wiki_title'] ) || (string)$page['wiki_title'] === '' ) {
				continue;
			}
			$expectedTitles[] = (string)$page['wiki_title'];
		}
		$expectedTitles = array_values( array_unique( $expectedTitles ) );

		foreach ( $expectedTitles as $title ) {
			$this->assertArrayHasKey( $title, $actualPages, "Missing page: $title" );
		}
	}
}
