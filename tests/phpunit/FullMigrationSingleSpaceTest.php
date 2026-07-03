<?php

namespace HalloWelt\MigrateConfluence\Tests;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Composer\ConfluenceComposer;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

class FullMigrationSingleSpaceTest extends TestCase {

	/** @var string */
	protected string $tempDir;

	/** @var string */
	protected string $dataDir;

	/** @var WorkspaceDB */
	protected WorkspaceDB $workspaceDB;

	/** @var MigrationConfig */
	protected MigrationConfig $migrationConfig;

	protected function setUp(): void {
		$this->dataDir = __DIR__ . '/data/FullMigration/SingleSource';

		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();

		// Single source migration test
		mkdir( $this->tempDir, 0755, true );
		mkdir( $this->tempDir . '/single-source', 0755, true );
		mkdir( $this->tempDir . '/single-source/input', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/raw', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/wikitext', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/result', 0755, true );
		mkdir( $this->tempDir . '/single-source/workspace/content/result/images', 0755, true );

		$sourceFile = $this->dataDir . '/input/entities.xml';
		copy( $sourceFile, $this->tempDir . '/single-source/input/entities.xml' );
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
		$config = file_exists( $configFile ) ? Yaml::parseFile( $configFile ) : [];

		// Step 1: Analyze
		$this->runAnalyze(
			$src,
			$dest,
			$config,
			$output
		);

		$workspaceDB = WorkspaceDB::openExisting( "$dest/workspace.sqlite", true );
		$pages = $workspaceDB->getPages();
		$this->assertCount( 6, $pages, "Analyze: Invalid number of pages found" );
		$blogPosts = $workspaceDB->getBlogPosts();
		$this->assertCount( 1, $blogPosts, "Analyze: Invalid number of blog_posts found" );

		$blogBodyContent = $workspaceDB->getBodyContentIdsForContentId( 10000001 );
		$this->assertCount( 1, $blogBodyContent, "Analyze: No bodyContent found for page id 20000003" );
		$blogBodyContentContent = $workspaceDB->getBodyContentIdsForContentId( 10000002 );
		$this->assertNotNull( $blogBodyContentContent, "Analyze: No bodyContent found for blog_post id 10000002" );

		// Step 2: Extract
		$this->runExtract(
			$src,
			$dest,
			$workspace,
			$config,
		);

		$pageTitle = $workspaceDB->getWikiPageTitleFromPageId( 10000030 );
		$this->assertEquals( 'CON:Create_Project_Page', $pageTitle );
		$blogPostTitle = $workspaceDB->getWikiBlogPostTitleFromBlogPostId( 10000002 );
		$this->assertEquals( 'Blog:CON/My_Blog_Post', $blogPostTitle );

		// Step 3: Convert
		$this->runConvert(
			$dest,
			$workspace,
			$config,
			$output
		);

		$blogPostRevisions = $workspaceDB->getBlogPostRevisionsForBlogPostId( 10000002 );
		$this->assertEquals( '["20000003"]', $blogPostRevisions[0]['body_content_ids'] );

		// Step 4: Compose
		$this->runCompose(
			$dest,
			$workspace,
			$config,
			$output
		);

		// Step 5: Verify
		$expectedPagesFile = $this->dataDir . '/expected/result_pages.xml';
		$expectedPages = $this->extractPages( $expectedPagesFile );

		$actualFile = $this->tempDir . '/single-source/workspace/result/CON/pages.xml';
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

		// Verify default-pages.xml
		$expectedTemplatesFile = $this->dataDir . '/expected/result_default-pages.xml';
		$expectedTemplates = $this->extractPages( $expectedTemplatesFile );

		$actualTemplatesFile = $this->tempDir . '/single-source/workspace/result/CON/default-pages.xml';
		$this->assertFileExists( $actualTemplatesFile );
		$actualTemplates = $this->extractPages( $actualTemplatesFile );

		$this->assertSame(
			array_keys( $expectedTemplates ),
			array_keys( $actualTemplates ),
			'Template titles do not match'
		);

		// Verify templates.xml
		$expectedTemplatesFile = $this->dataDir . '/expected/result_templates.xml';
		$expectedTemplates = $this->extractPages( $expectedTemplatesFile );

		$actualTemplatesFile = $this->tempDir . '/single-source/workspace/result/CON/templates.xml';
		$this->assertFileExists( $actualTemplatesFile );
		$actualTemplates = $this->extractPages( $actualTemplatesFile );

		$this->assertSame(
			array_keys( $expectedTemplates ),
			array_keys( $actualTemplates ),
			'Template titles do not match'
		);

		foreach ( $expectedTemplates as $title => $expectedTemplateXml ) {
			$this->assertArrayHasKey( $title, $actualTemplates, "Missing template: $title" );
			$this->assertXmlStringEqualsXmlString(
				$expectedTemplateXml,
				$actualTemplates[$title],
				"Template content mismatch for: $title"
			);
		}

		// Verify comments.xml
		$expectedCommentsFile = $this->dataDir . '/expected/result_comments.xml';
		$actualCommentsFile = $this->tempDir . '/single-source/workspace/result/CON/comments.xml';
		$this->assertFileExists( $actualCommentsFile );
		$this->assertXmlFileEqualsXmlFile( $expectedCommentsFile, $actualCommentsFile );
	}

	/**
	 * @param string $src
	 * @param string $dest
	 * @param array $config
	 * @param BufferedOutput $output
	 *
	 * @return void
	 */
	protected function runAnalyze(
		string $src,
		string $dest,
		array $config,
		BufferedOutput $output,
	): void {
		$dbPath = $dest . '/workspace.sqlite';
		$workspaceDB = file_exists( $dbPath )
			? WorkspaceDB::openExisting( $dbPath )
			: WorkspaceDB::createNew(
				$dbPath
			);
		$writer = new AnalyzeDirectDataWriter( $workspaceDB );

		if ( isset( $config['config'] ) ) {
			$migrationConfig = new MigrationConfig( $config['config'] );
		} else {
			$migrationConfig = new MigrationConfig( [] );
		}

		$analyzer = new ConfluenceAnalyzer( $writer, $workspaceDB, $output, $migrationConfig );
		$analyzer->analyze( new SplFileInfo( $src . '/entities.xml' ) );
	}

	/**
	 * @param string $src
	 * @param string $dest
	 * @param Workspace $workspace
	 * @param array $config
	 *
	 * @return void
	 */
	protected function runExtract(
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
	 *
	 * @return void
	 */
	protected function runConvert(
		string $dest,
		Workspace $workspace,
		array $config,
		BufferedOutput $output,
	): void {
		$pipe = fopen( 'php://temp', 'w+' );
		if ( $pipe === false ) {
			throw new \RuntimeException( 'Unable to open temporary pipe for converter test run' );
		}

		$rawFiles = glob( $dest . '/content/raw/*.mraw' );
		foreach ( $rawFiles as $rawFilePath ) {
			$converter = new ConfluenceConverter( $config, $workspace );
			$converter->setPipe( $pipe );
			$converter->setDestinationPath( $dest );
			$converter->setOutput( $output );

			$wikiText = $converter->convert( new SplFileInfo( $rawFilePath ) );

			$id = basename( $rawFilePath, '.mraw' );
			file_put_contents( $dest . '/content/wikitext/' . $id . '.wiki', $wikiText );
		}

		fclose( $pipe );
		$this->ensureAllReferencedBodyContentsHaveWikiFiles( $dest );
	}

	/**
	 * Ensure compose can load converted content for every referenced body content ID.
	 * Some fixtures contain references without extractable raw source; in tests we
	 * create empty placeholders so compose can proceed deterministically.
	 *
	 * @param string $dest
	 *
	 * @return void
	 */
	protected function ensureAllReferencedBodyContentsHaveWikiFiles( string $dest ): void {
		$workspaceDB = WorkspaceDB::openExisting( $dest . '/workspace.sqlite' );

		$entities = array_merge(
			$workspaceDB->getPages(),
			$workspaceDB->getBlogPosts(),
			$workspaceDB->getSpaceDescriptions(),
			$workspaceDB->getPageTemplates(),
			$workspaceDB->getComments()
		);

		foreach ( $entities as $entity ) {
			if ( !isset( $entity['body_content_ids'] ) ) {
				continue;
			}

			$bodyContentIds = json_decode( (string)$entity['body_content_ids'], true );
			if ( !is_array( $bodyContentIds ) ) {
				continue;
			}

			foreach ( $bodyContentIds as $bodyContentId ) {
				if ( $bodyContentId === '' ) {
					continue;
				}

				$wikiFile = $dest . '/content/wikitext/' . $bodyContentId . '.wiki';
				if ( file_exists( $wikiFile ) ) {
					continue;
				}

				file_put_contents( $wikiFile, '' );
			}
		}
	}

	/**
	 * @param string $dest
	 * @param Workspace $workspace
	 * @param array $config
	 * @param BufferedOutput $output
	 *
	 * @return void
	 */
	protected function runCompose(
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
	 *
	 * @return array title => page XML string, sorted by title
	 */
	protected function extractPages( string $xmlFile ): array {
		$dom = new DOMDocument();
		$dom->load( $xmlFile );
		$xpath = new DOMXPath( $dom );

		$pages = [];
		foreach ( $xpath->query( '//page' ) as $pageNode ) {
			$titleNodes = $xpath->query( 'title', $pageNode );
			$title = $titleNodes->item( 0 )->textContent;

			$pages[$title] = $dom->saveXML( $pageNode );
		}
		ksort( $pages );

		return $pages;
	}

	/**
	 * @param string $dir
	 */
	protected function removeDirectory( string $dir ): void {
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
