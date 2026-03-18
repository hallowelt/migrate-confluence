<?php

namespace HalloWelt\MigrateConfluence\Tests\FullMigration;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Composer\ConfluenceComposer;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;
use HalloWelt\MigrateConfluence\Extractor\ConfluenceExtractor;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\BufferedOutput;

class FullMigrationTest extends TestCase {

	/** @var string */
	private $workDir;

	protected function setUp(): void {
		$this->workDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();
		mkdir( $this->workDir, 0755, true );
		mkdir( $this->workDir . '/result/images', 0755, true );
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
	public function testExternalImageUrlMigration(): void {
		$sourceFile = __DIR__ . '/external_image_url_export_source.xml';
		$expectedFile = __DIR__ . '/external_image_url_export_result.xml';

		copy( $sourceFile, $this->workDir . '/entities.xml' );

		$workspace = new Workspace( new SplFileInfo( $this->workDir ) );
		$output = new BufferedOutput();
		$config = [];

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

		$expectedPages = $this->extractPages( $expectedFile );
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
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param BufferedOutput $output
	 */
	private function runAnalyze( array $config, Workspace $workspace, BufferedOutput $output ): void {
		$buckets = new DataBuckets( [
			'global-files',
		] );

		$analyzer = new ConfluenceAnalyzer( $config, $workspace, $buckets );
		$analyzer->setOutput( $output );
		$analyzer->analyze( new SplFileInfo( $this->workDir . '/entities.xml' ) );

		$buckets->saveToWorkspace( $workspace );
	}

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 */
	private function runExtract( array $config, Workspace $workspace ): void {
		$buckets = new DataBuckets( [
			'global-title-metadata',
			'global-attachment-metadata',
			'global-revision-contents',
			'global-body-content-id-to-page-id-map',
			'global-body-content-id-to-space-description-id-map',
		] );

		$extractor = new ConfluenceExtractor( $config, $workspace, $buckets );
		$extractor->extract( new SplFileInfo( $this->workDir . '/entities.xml' ) );

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
