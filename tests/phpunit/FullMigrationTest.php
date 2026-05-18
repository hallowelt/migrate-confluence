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
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

class FullMigrationTest extends TestCase {

	/** @var string */
	private string $workDir;

	/** @var string */
	private string $dataDir;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	protected function setUp(): void {
		$this->workDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();
		mkdir( $this->workDir, 0755, true );
		mkdir( $this->workDir . '/result/images', 0755, true );

		$this->dataDir = __DIR__ . '/data/FullMigration';
	}

	protected function tearDown(): void {
		$this->removeDirectory( $this->workDir );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer
	 * @covers \HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter
	 * @covers \HalloWelt\MigrateConfluence\Composer\ConfluenceComposer
	 */
	public function testMigrationWithMultipleExportDirectories(): void {
		$sourceDir = $this->dataDir . '/MultipleExports';
		$expectedPagesFile = $sourceDir . '/result_pages.xml';

		$this->migrationConfig = new MigrationConfig( [] );
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		$subDirs = [ 'space_alpha', 'space_beta', 'space_gamma' ];
		foreach ( $subDirs as $subDir ) {
			mkdir( $this->workDir . '/' . $subDir, 0755, true );
			copy(
				$sourceDir . '/' . $subDir . '/entities.xml',
				$this->workDir . '/' . $subDir . '/entities.xml'
			);
		}

		$workspace = new Workspace( new SplFileInfo( $this->workDir ) );
		$output = new BufferedOutput();
		$config = [];

		// Step 1: Analyze all export directories first so we can assert the
		// accumulated workspace state before extraction begins.
		foreach ( $subDirs as $subDir ) {
			$entitiesFile = $this->workDir . '/' . $subDir . '/entities.xml';
			$this->runAnalyze( $config, $workspace, $output, $entitiesFile );
		}

		$pages = $this->workspaceDB->getPages();

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
		foreach ( $subDirs as $subDir ) {
			$entitiesFile = $this->workDir . '/' . $subDir . '/entities.xml';
			$this->runExtract( $config, $workspace, $entitiesFile );
		}

		// Step 3: Convert
		$this->runConvert( $config, $workspace, $output );

		// Step 4: Compose
		$this->runCompose( $config, $workspace, $output );

		// Step 5: Verify that the unique pages from each export appear in the output.
		// "Shared Page" intentionally excluded: it has two body-content revisions
		// and its exact output format is verified separately.
		$actualFile = $this->workDir . '/result/pages.xml';
		$this->assertFileExists( $actualFile );

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
	 * @covers \HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer
	 * @covers \HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter
	 * @covers \HalloWelt\MigrateConfluence\Composer\ConfluenceComposer
	 */
	public function testMigration(): void {
		$sourceFile = $this->dataDir . '/export_source.xml';
		$expectedPagesFile = $this->dataDir . '/result_pages.xml';
		$expectedCommentsFile = $this->dataDir . '/result_comments.xml';

		$this->migrationConfig = new MigrationConfig( [] );
		$this->workspaceDB = ( new WorkspaceDbMock() )->createEmpty();

		copy( $sourceFile, $this->workDir . '/entities.xml' );

		$workspace = new Workspace( new SplFileInfo( $this->workDir ) );
		$output = new BufferedOutput();
		$configFile = $this->dataDir . '/config.yaml';
		$config = file_exists( $configFile )
			? Yaml::parseFile( $configFile )
			: [];

		// Step 1: Analyze
		$this->runAnalyze( $config, $workspace, $output );

		// Step 2: Extract
		$this->runExtract( $config, $workspace );

		// Step 3: Convert
		$this->runConvert( $config, $workspace, $output );

		// Step 4: Compose
		$this->runCompose( $config, $workspace, $output );

		// Step 5: Verify
		$actualFile = $this->workDir . '/result/pages.xml';
		$this->assertFileExists( $actualFile );

		$expectedPages = $this->extractPages( $expectedPagesFile );
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
		$actualCommentsFile = $this->workDir . '/result/comments.xml';
		$this->assertFileExists( $actualCommentsFile );
		$this->assertXmlFileEqualsXmlFile( $expectedCommentsFile, $actualCommentsFile );
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param BufferedOutput $output
	 * @param string|null $entitiesFilePath defaults to <workDir>/entities.xml
	 */
	private function runAnalyze(
		array $config,
		Workspace $workspace,
		BufferedOutput $output,
		?string $entitiesFilePath = null
	): void {
		$entitiesFilePath ??= $this->workDir . '/entities.xml';

		$buckets = new DataBuckets( [
			'global-files',
		] );

		$analyzer = new ConfluenceAnalyzer( $config, $workspace, $buckets );
		$analyzer->setOutput( $output );
		$analyzer->analyze( new SplFileInfo( $entitiesFilePath ) );

		$buckets->saveToWorkspace( $workspace );
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param string|null $entitiesFilePath defaults to <workDir>/entities.xml
	 */
	private function runExtract(
		array $config,
		Workspace $workspace,
		?string $entitiesFilePath = null
	): void {
		$entitiesFilePath ??= $this->workDir . '/entities.xml';

		$buckets = new DataBuckets( [
			'global-title-metadata',
			'global-attachment-metadata',
			'global-revision-contents',
			'global-body-content-id-to-page-id-map',
			'global-body-content-id-to-space-description-id-map',
			'global-body-content-id-to-comment-id-map',
		] );

		$extractor = new ConfluenceExtractor( $config, $workspace, $buckets );
		$extractor->extract( new SplFileInfo( $entitiesFilePath ) );

		$buckets->saveToWorkspace( $workspace );
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param BufferedOutput $output
	 */
	private function runConvert( array $config, Workspace $workspace, BufferedOutput $output ): void {
		$rawDir = $this->workDir . '/content/raw';
		if ( !is_dir( $rawDir ) ) {
			return;
		}

		$wikiDir = $this->workDir . '/content/wikitext';
		if ( !is_dir( $wikiDir ) ) {
			mkdir( $wikiDir, 0755, true );
		}

		$rawFiles = glob( $rawDir . '/*.mraw' );
		foreach ( $rawFiles as $rawFilePath ) {
			$converter = new ConfluenceConverter( $config, $workspace );
			$converter->setOutput( $output );

			$wikiText = $converter->convert( new SplFileInfo( $rawFilePath ) );

			$id = basename( $rawFilePath, '.mraw' );
			file_put_contents( $wikiDir . '/' . $id . '.wiki', $wikiText );
		}
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param BufferedOutput $output
	 */
	private function runCompose( array $config, Workspace $workspace, BufferedOutput $output ): void {
		$buckets = new DataBuckets( [
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-body-content-id-to-space-description-id-map',
			'global-body-content-id-to-page-id-map',
			'global-title-attachments',
			'global-title-revisions',
			'global-files',
			'global-additional-files',
			'global-page-id-to-comment-ids-map',
			'global-comment-id-to-metadata-map',
			'global-page-id-to-title-map',
			'global-userkey-to-username-map',
		] );
		$buckets->loadFromWorkspace( $workspace );

		$composer = new ConfluenceComposer( $config, $workspace, $buckets );
		$composer->setOutput( $output );
		$composer->setDestinationPath( $this->workDir );

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
