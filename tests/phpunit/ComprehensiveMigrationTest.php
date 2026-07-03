<?php

namespace HalloWelt\MigrateConfluence\Tests;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Composer\ConfluenceComposer;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor;
use HalloWelt\MigrateConfluence\Tests\Database\ComprehensiveMockDatabase;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Yaml\Yaml;

class ComprehensiveMigrationTest extends TestCase {

	/** @var string */
	protected string $tempDir;

	/** @var WorkspaceDB */
	protected WorkspaceDB $workspaceDB;

	protected function setUp(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-comprehensive-' . uniqid();

		// Create directory structure
		mkdir( $this->tempDir, 0755, true );
		mkdir( $this->tempDir . '/workspace', 0755, true );
		mkdir( $this->tempDir . '/workspace/content', 0755, true );
		mkdir( $this->tempDir . '/workspace/content/raw', 0755, true );
		mkdir( $this->tempDir . '/workspace/content/wikitext', 0755, true );
		mkdir( $this->tempDir . '/workspace/content/result', 0755, true );
		mkdir( $this->tempDir . '/workspace/content/result/images', 0755, true );

		// Create comprehensive mock database in the workspace directory
		$mockDatabase = new ComprehensiveMockDatabase();
		$this->workspaceDB = $mockDatabase->createInDirectory( $this->tempDir . '/workspace' );
	}

	protected function tearDown(): void {
		$this->removeDirectory( $this->tempDir );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor
	 * @covers \HalloWelt\MigrateConfluence\Converter\ConfluenceConverter
	 * @covers \HalloWelt\MigrateConfluence\Composer\ConfluenceComposer
	 */
	public function testComprehensiveMigration(): void {
		$dest = $this->tempDir . '/workspace';

		$workspace = new Workspace(
			new SplFileInfo( $dest )
		);

		$output = new BufferedOutput();

		$config = [
			'extractors' => [],
			'converters' => [],
			'composers' => [],
		];

		// Step 1: Verify database was created correctly
		$this->verifyDatabaseStructure();

		// Step 2: Extract (creates .mraw files from database)
		$this->runExtract(
			$dest,
			$workspace,
			$config
		);

		// Debug: Check database state after extract
		$dbAfterExtract = new WorkspaceDB( $dest . '/workspace.sqlite' );
		$pagesAfterExtract = $dbAfterExtract->getPages();
		error_log( "DEBUG: After extract, database has " . count( $pagesAfterExtract ) . " pages" );
		if ( count( $pagesAfterExtract ) > 0 ) {
			$firstPage = reset( $pagesAfterExtract );
			error_log( "DEBUG: First page after extract: " . json_encode( $firstPage ) );
		}
		// Check for null wiki_titles
		$nullWikiTitles = 0;
		foreach ( $pagesAfterExtract as $page ) {
			if ( empty( $page['wiki_title'] ) ) {
				$nullWikiTitles++;
			}
		}
		error_log( "DEBUG: Pages with null/empty wiki_title: " . $nullWikiTitles );

		// Verify raw files were created
		$rawFiles = glob( $dest . '/content/raw/*.mraw' );
		$this->assertGreaterThan( 0, count( $rawFiles ), "Extract: No raw extraction files created" );

		// Step 3: Convert (converts .mraw to .wiki files)
		$this->runConvert(
			$dest,
			$workspace,
			$config,
			$output
		);

		// Verify wiki files were created
		$wikiFiles = glob( $dest . '/content/wikitext/*.wiki' );
		$this->assertGreaterThan( 0, count( $wikiFiles ), "Convert: No wiki text files created" );

		// Step 4: Compose (builds final MediaWiki XML)
		$this->runCompose(
			$dest,
			$workspace,
			$config,
			$output
		);

		// Step 5: Verify output structure
		$this->verifyOutputStructure( $dest );
	}

	/**
	 * Verify the mock database was created with correct structure
	 */
	private function verifyDatabaseStructure(): void {
		$pages = $this->workspaceDB->getPages();
		$this->assertCount( 40, $pages, "Database: Invalid number of pages found (expected 40, 10 per 4 spaces)" );

		$blogPosts = $this->workspaceDB->getBlogPosts();
		$this->assertCount( 20, $blogPosts, "Database: Invalid number of blog posts found (expected 20, 5 per 4 spaces)" );

		// Verify space count
		$spaces = $this->workspaceDB->getSpaces();
		$this->assertCount( 4, $spaces, "Database: Invalid number of spaces found (expected 4)" );

		// Debug: Show first page
		$firstPage = reset( $pages );
		error_log( "First page: " . json_encode( $firstPage ) );

		// Find pages without wiki_title
		$pagesWithoutWikiTitle = [];
		foreach ( $pages as $page ) {
			if ( empty( $page['wiki_title'] ) || $page['wiki_title'] === null ) {
				$pagesWithoutWikiTitle[] = $page['page_id'];
			}
		}
		if ( !empty( $pagesWithoutWikiTitle ) ) {
			error_log( "Pages without wiki_title: " . json_encode( $pagesWithoutWikiTitle ) );
		}

		// Find blog posts without wiki_title
		$blogsWithoutWikiTitle = [];
		foreach ( $blogPosts as $blog ) {
			if ( empty( $blog['wiki_title'] ) || $blog['wiki_title'] === null ) {
				$blogsWithoutWikiTitle[] = $blog['page_id'];
			}
		}
		if ( !empty( $blogsWithoutWikiTitle ) ) {
			error_log( "Blog posts without wiki_title: " . json_encode( $blogsWithoutWikiTitle ) );
		}

		// Verify each page has the expected title format
		foreach ( $pages as $page ) {
			$this->assertNotEmpty( $page['wiki_title'], "Page has no wiki_title. Page data: " . json_encode( $page ) );
			// Wiki title format: <namespace>:Page_<number>
			$pattern = '/^[A-Z0-9]+:Page_\d+$/';
			$match = preg_match( $pattern, $page['wiki_title'] );
			$this->assertGreaterThan(
				0,
				$match,
				"Page title format incorrect: " . $page['wiki_title'] . " (pattern: " . $pattern . ", match result: " . var_export( $match, true ) . ")"
			);

			// Verify each page has revisions (body_content_ids)
			$bodyContentIds = json_decode( $page['body_content_ids'], true );
			$this->assertIsArray( $bodyContentIds, "Page body_content_ids should be JSON array" );
			$this->assertGreaterThan( 0, count( $bodyContentIds ), "Page should have at least one revision" );
		}

		// Verify each blog post has the expected title format
		foreach ( $blogPosts as $blog ) {
			$this->assertNotEmpty( $blog['wiki_title'], "Blog post has no wiki_title" );
			// Wiki title format: Blog:<namespace>/Blog_Post_<number>
			$pattern = '/^Blog:[A-Z0-9]+\/Blog_Post_\d+$/';
			$match = preg_match( $pattern, $blog['wiki_title'] );
			$this->assertGreaterThan(
				0,
				$match,
				"Blog post title format incorrect: " . $blog['wiki_title'] . " (pattern: " . $pattern . ")"
			);

			// Verify each blog has revisions
			$bodyContentIds = json_decode( $blog['body_content_ids'], true );
			$this->assertIsArray( $bodyContentIds, "Blog post body_content_ids should be JSON array" );
			$this->assertGreaterThan( 0, count( $bodyContentIds ), "Blog post should have at least one revision" );
		}
	}

	private function verifyOutputStructure( string $dest ): void {
		$resultDir = $dest . '/content/result';

		// Verify we have directories for each space
		$expectedSpaceKeys = [ 'SPACE1', 'SPACE2', 'SPACE3', 'SPACE4' ];
		foreach ( $expectedSpaceKeys as $spaceKey ) {
			$spaceDir = $resultDir . '/' . $spaceKey;
			$this->assertDirectoryExists( $spaceDir, "Output directory missing for space: $spaceKey" );

			// Verify pages.xml exists
			$pagesXmlFile = $spaceDir . '/pages.xml';
			$this->assertFileExists( $pagesXmlFile, "pages.xml missing for space: $spaceKey" );

			$pages = $this->extractPages( $pagesXmlFile );
			$this->assertCount( 10, $pages, "Expected 10 pages in space $spaceKey, got " . count( $pages ) );

			// Verify specific page titles from our seed data
			$spaceNum = substr( $spaceKey, -1 ); // Extract number from SPACE1, SPACE2, etc.
			for ( $pageNum = 1; $pageNum <= 10; $pageNum++ ) {
				$expectedTitle = "SPACE{$spaceNum}:Page_{$pageNum}";
				$this->assertArrayHasKey( $expectedTitle, $pages, "Missing expected page title: $expectedTitle in space $spaceKey" );

				// Verify each page has revisions in the XML
				$pageXml = $pages[$expectedTitle];
				$this->assertStringContainsString( 'revision', $pageXml, "Page $expectedTitle missing revision information" );
			}

			// Verify blogs.xml if it exists
			$blogsXmlFile = $spaceDir . '/blogs.xml';
			if ( file_exists( $blogsXmlFile ) ) {
				$this->assertFileReadable( $blogsXmlFile, "blogs.xml not readable for space: $spaceKey" );

				$blogs = $this->extractPages( $blogsXmlFile );
				$this->assertGreaterThan( 0, count( $blogs ), "Expected blogs in space $spaceKey, but got none" );

				// Verify specific blog titles
				for ( $blogNum = 1; $blogNum <= 5; $blogNum++ ) {
					$expectedTitle = "Blog:SPACE{$spaceNum}/Blog_Post_{$blogNum}";
					$this->assertArrayHasKey( $expectedTitle, $blogs, "Missing expected blog title: $expectedTitle in space $spaceKey" );
				}
			}

			// Verify comments files if they exist
			$pageCommentsFile = $spaceDir . '/page_comments.xml';
			if ( file_exists( $pageCommentsFile ) ) {
				$this->assertFileReadable( $pageCommentsFile, "page_comments.xml not readable for space: $spaceKey" );
			}

			$blogCommentsFile = $spaceDir . '/blog_comments.xml';
			if ( file_exists( $blogCommentsFile ) ) {
				$this->assertFileReadable( $blogCommentsFile, "blog_comments.xml not readable for space: $spaceKey" );
			}
		}
	}

	/**
	 * Extract pages from an XML file
	 * @param string $xmlFile
	 * @return array title => page XML string
	 */
	protected function extractPages( string $xmlFile ): array {
		if ( !file_exists( $xmlFile ) ) {
			return [];
		}

		$dom = new DOMDocument();
		$dom->load( $xmlFile );
		$xpath = new DOMXPath( $dom );

		$pages = [];
		foreach ( $xpath->query( '//page' ) as $pageNode ) {
			$titleNodes = $xpath->query( 'title', $pageNode );
			if ( $titleNodes->length > 0 ) {
				$title = $titleNodes->item( 0 )->textContent;
				$pages[$title] = $dom->saveXML( $pageNode );
			}
		}

		return $pages;
	}

	/**
	 * Run Extract step
	 */
	protected function runExtract(
		string $dest,
		Workspace $workspace,
		array $config
	): void {
		$buckets = new DataBuckets( [] );

		$extractor = new ConfluenceExtractor( $config, $workspace, $buckets );
		$extractor->setDestinationPath( $dest );
		// Extract reads from workspace.sqlite which was already populated by ComprehensiveMockDatabase
		// Pass workspace dir as a dummy file (the extractor mainly processes the database, not the file)
		$extractor->extract( new SplFileInfo( $dest ) );
	}

	/**
	 * Run Convert step
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
	 * Ensure compose can load converted content for every referenced body content ID
	 */
	protected function ensureAllReferencedBodyContentsHaveWikiFiles( string $dest ): void {
		$workspaceDB = new WorkspaceDB( $dest . '/workspace.sqlite' );

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
	 * Run Compose step
	 */
	protected function runCompose(
		string $dest,
		Workspace $workspace,
		array $config,
		BufferedOutput $output,
	): void {
		// Debug: Check database state before Compose
		$workspaceDB = new WorkspaceDB( $dest . '/workspace.sqlite' );
		$pages = $workspaceDB->getPages();
		error_log( "\nDEBUG: Pages before Compose: " . count( $pages ) );
		$nullTitles = [];
		foreach ( $pages as $page ) {
			if ( empty( $page['wiki_title'] ) ) {
				$nullTitles[] = $page['page_id'];
			}
		}
		if ( !empty( $nullTitles ) ) {
			error_log( "DEBUG: Pages with null wiki_title: " . implode( ', ', $nullTitles ) );
		}

		// Check page attachments and which pages they belong to
		try {
			$allPageAttachments = [];
			for ( $spaceId = 1; $spaceId <= 4; $spaceId++ ) {
				$pageAttachments = $workspaceDB->getPageAttachments( $spaceId );
				error_log( "DEBUG: Space $spaceId has " . count( $pageAttachments ) . " page attachments" );
				foreach ( $pageAttachments as $att ) {
					if ( !isset( $allPageAttachments[$att['page_id']] ) ) {
						$allPageAttachments[$att['page_id']] = 0;
					}
					$allPageAttachments[$att['page_id']]++;
				}
			}

			// Check which attachment pages have null wiki_titles
			foreach ( $allPageAttachments as $pageId => $count ) {
				$wikiTitle = $workspaceDB->getWikiPageTitleFromPageId( (int)$pageId );
				if ( empty( $wikiTitle ) ) {
					error_log( "DEBUG: PROBLEM - Page $pageId has $count attachments but wiki_title is null" );
				}
			}
		} catch ( \Exception $e ) {
			error_log( "DEBUG: Error checking attachments: " . $e->getMessage() );
		}

		$buckets = new DataBuckets( [] );

		$composer = new ConfluenceComposer( $config, $workspace, $buckets );
		$composer->setOutput( $output );
		$composer->setDestinationPath( $dest );

		$builder = new \HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder();
		$composer->buildXML( $builder );
	}

	/**
	 * Recursively remove directory
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
