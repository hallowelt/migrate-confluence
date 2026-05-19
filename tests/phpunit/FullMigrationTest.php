<?php

namespace HalloWelt\MigrateConfluence\Tests;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Composer\ConfluenceComposer;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

class FullMigrationTest extends TestCase {

	/** @var string */
	private string $tempDir;

	/** @var string */
	private string $dataDir;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	protected function setUp(): void {
		$this->dataDir = __DIR__ . '/data/FullMigration';

		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();

		// Single source migration test
		mkdir( $this->tempDir, 0755, true );
		mkdir( $this->tempDir . '/single-source', 0755, true );
		mkdir( $this->tempDir . '/single-source/input', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/raw', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/wikitext', 0755, true );

		$sourceFile = $this->dataDir . '/SingleSource/input/entities.xml';
		copy( $sourceFile,  $this->tempDir . '/single-source/input/entities.xml' );

		// Multi source migration test
		mkdir( $this->tempDir . '/multi-source', 0755, true );
		mkdir( $this->tempDir . '/multi-source/input', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/raw', 0755, true );
		mkdir( $this->tempDir . '/multi-source/workspace/content/wikitext', 0755, true );

		$spaces = [ 'space_alpha', 'space_beta', 'space_gamma' ];
		foreach ( $spaces as $space	 ) {
			$sourceFile = $this->dataDir . '/MultiSource/input/' . $space . '/entities.xml';
			mkdir( $this->tempDir . '/multi-source/input/' . $space, 0755, true );
			copy( $sourceFile,  $this->tempDir . '/multi-source/input/' . $space . '/entities.xml' );
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
		$src = $this->tempDir . '/single-source/input';
		$dest = $this->tempDir . '/single-source/workspace';

		$workspace = new Workspace(
			new SplFileInfo( $this->tempDir . '/single-source/workspace' )
		);

		$output = new BufferedOutput();

		$configFile = $this->dataDir . '/config.yaml';
		$config = file_exists( $configFile )
			? Yaml::parseFile( $configFile )
			: [];

		// Step 1: Analyze
		$this->runAnalyze(
			$src,
			$dest,
			$workspace,
			$config,
			$output
		);

		// Step 2: Extract
		$this->runExtract(
			$src,
			$dest,
			$workspace,
			$config,
		);

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

		// Step 5: Verify
		$expectedPagesFile = $this->dataDir . '/result_pages.xml';
		$expectedPages = $this->extractPages( $expectedPagesFile );

		$actualFile = $this->tempDir . '/single-source/workspace/result/pages.xml';
		$this->assertFileExists( $actualFile );
		$actualPages = $this->extractPages( $actualFile );

		$this->assertSame(
			array_keys( $expectedPages ),
			array_keys( $actualPages ),
			'Page titles do not match'
		);

		foreach ( $expectedPages as $title => $expectedPageXml ) {
			$this->assertArrayHasKey( $title, $actualPages, "Missing page: $title" );
			$this->assertXmlStringEqualsXmlString(
				$expectedPageXml,
				$actualPages[$title],
				"Page content mismatch for: $title"
			);
		}

		// Verify comments.xml
		$expectedCommentsFile = $this->dataDir . '/result_comments.xml';
		$actualCommentsFile = $this->tempDir . '/single-source/workspace/result/comments.xml';
		$this->assertFileExists( $actualCommentsFile );
		$this->assertXmlFileEqualsXmlFile( $expectedCommentsFile, $actualCommentsFile );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer
	 * @covers \HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter
	 * @covers \HalloWelt\MigrateConfluence\Composer\ConfluenceComposer
	 */
	public function testMigrationWithMultipleExportDirectories(): void {
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
				$workspace,
				$config,
				$output
			);
		}

		$resultWorkspaceDB = new WorkspaceDB( $this->tempDir . '/multi-source/workspace/workspace.db' );
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
			'70000000---Beta Page',
			$titlesMap,
			'Beta Page (unique to space_beta export) was lost from analyze-pages-titles-map'
		);

		// analyze-page-id-to-confluence-key-map: every page ID must resolve to
		// its spaceId---NormalizedLeafTitle key irrespective of hierarchy.
		$idToConfluenceKeyMap = [];
		foreach ( $pages as $page ) {
			$id = $page['id'];
			$spaceId = $page['space_id'];
			$confluenceTitle = $page['confluence_title'];

			$key = "$spaceId---$confluenceTitle";

			$idToConfluenceKeyMap[$id] = $key;
		}

		$this->assertSame(
			'70000000---Duplicate Title Page',
			$idToConfluenceKeyMap[70000020] ?? null,
			'Parent page 70000020 must be present in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'70000000---Duplicate Title Page',
			$idToConfluenceKeyMap[70000021] ?? null,
			'Child page 70000021 shares the parent title and must map to the same confluence key'
		);
		$this->assertSame(
			'70000000---Duplicate Child Page',
			$idToConfluenceKeyMap[70000022] ?? null,
			'Child page 70000022 must be present in analyze-page-id-to-confluence-key-map'
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
			'70000000---Same Space Title',
			$idToConfluenceKeyMap[70000031] ?? null,
			'Page 70000031 ("Same Space Title") must be in analyze-page-id-to-confluence-key-map'
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
			'70000100---Shared Child Title',
			$idToConfluenceKeyMap[70000051] ?? null,
			'Page 70000051 (ParentB/ChildA, space BETA) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertArrayHasKey(
			'70000000---Shared Child Title',
			$titlesMap,
			'ParentA/ChildA (space ALPHA) must be in analyze-pages-titles-map'
		);
		
		$this->assertArrayHasKey(
			'70000100---Shared Child Title',
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
			'70000000---Deep ABC',
			$idToConfluenceKeyMap[70000061] ?? null,
			'Page 70000061 (Deep ABC level 2) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertSame(
			'70000000---Deep ABC',
			$idToConfluenceKeyMap[70000062] ?? null,
			'Page 70000062 (Deep ABC level 3) must be in analyze-page-id-to-confluence-key-map'
		);
		$this->assertArrayHasKey(
			'70000000---Deep ABC',
			$titlesMap,
			'First "Deep ABC" page must remain in analyze-pages-titles-map'
		);

		// Step 2: Extract each entities.xml
		foreach ( $spaces as $space ) {
			$src = $this->tempDir . '/input/' . $space;
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

		$expectedPagesFile = $this->dataDir . '/result_pages.xml';
		$expectedPages = $this->extractPages( $expectedPagesFile );
		$actualPages = $this->extractPages( $actualFile );

		foreach ( $expectedPages as $title => $expectedPageXml ) {
			$this->assertArrayHasKey( $title, $actualPages, "Missing page: $title" );
			$this->assertXmlStringEqualsXmlString(
				$expectedPageXml,
				$actualPages[$title],
				"Page content mismatch for: $title"
			);
		}
	}

	/**
	 * @param string $src
	 * @param Workspace $workspace
	 * @param array $config
	 * @param BufferedOutput $output
	 * @param string $dest
	 * @return void
	 */
	private function runAnalyze(
		string $src,
		string $dest,
		Workspace $workspace,
		array $config,
		BufferedOutput $output,
	): void {
		$buckets = new DataBuckets( [] );
		$output = new BufferedOutput();

		$analyzer = new ConfluenceAnalyzer( $config, $workspace, $buckets );
		$analyzer->setOutput( $output );
		$analyzer->setDestinationPath( $dest );
		$analyzer->analyze( new SplFileInfo( $src . '/entities.xml' ) );
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param string|null $entitiesFilePath defaults to <workDir>/entities.xml
	 */
	private function runExtract(
		string $src,
		string $dest,
		Workspace $workspace,
		array $config
	): void {
		$buckets = new DataBuckets( [] );

		$extractor = new ConfluenceExtractor( $config, $workspace, $buckets );
		$extractor->setDestinationPath( $dest );
		$extractor->extract( new SplFileInfo( $src . '/entities.xml' ) );
	}

	/**
	 * @param string $dest
	 * @param Workspace $workspace
	 * @param array $config
	 * @param BufferedOutput $output
	 * @return void
	 */
	private function runConvert(
		string $dest,
		Workspace $workspace,
		array $config,
		BufferedOutput $output,
	): void {
		$rawFiles = glob( $dest . '/content/raw/*.mraw' );
		foreach ( $rawFiles as $rawFilePath ) {
			$converter = new ConfluenceConverter( $config, $workspace );
			$converter->setDestinationPath( $dest );
			$converter->setOutput( $output );

			$wikiText = $converter->convert( new SplFileInfo( $rawFilePath ) );

			$id = basename( $rawFilePath, '.mraw' );
			file_put_contents( $dest . '/content/wikitext/' . $id . '.wiki', $wikiText );
		}
	}

	/**
	 * @param string $dest
	 * @param Workspace $workspace
	 * @param array $config
	 * @param BufferedOutput $output
	 * @return void
	 */
	private function runCompose(
		string $dest,
		Workspace $workspace,
		array $config,
		BufferedOutput $output,
	): void {
		$buckets = new DataBuckets( [] );

		$composer = new ConfluenceComposer( $config, $workspace, $buckets );
		$composer->setOutput( $output );
		$composer->setDestinationPath( $dest );

		$builder = new Builder();
		$composer->buildXML( $builder );
	}

	/**
	 * @param string $xmlFile
	 * @return array title => page XML string, sorted by title
	 */
	private function extractPages( string $xmlFile ): array {
		$dom = new DOMDocument();
		$dom->load( $xmlFile );
		$xpath = new DOMXPath( $dom );

		$pages = [];
		foreach ( $xpath->query( '//page' ) as $pageNode ) {
			$titleNodes = $xpath->query( 'title', $pageNode );
			$title = $titleNodes->item( 0 )->textContent;

			// Ignore Templates becaus otherwise each new Template
			// will break the test
			if ( str_starts_with( $title, 'Template:' ) ) {
				continue;
			}

			$pages[$title] = $dom->saveXML( $pageNode );
		}
		ksort( $pages );

		return $pages;
	}

	/**
	 * @param string $dir
	 */
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
