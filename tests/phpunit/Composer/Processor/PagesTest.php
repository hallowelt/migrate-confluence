<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Console\Output\Output;

class PagesTest extends TestCase {

	/** @var string */
	private $tmpDir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/migrate-confluence-pages-test-' . uniqid( '', true );
		mkdir( $this->tmpDir . '/workspace/content/wikitext', 0755, true );
		mkdir( $this->tmpDir . '/result', 0755, true );
	}

	protected function tearDown(): void {
		$this->deleteDir( $this->tmpDir );
		parent::tearDown();
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\Pages::execute
	 */
	public function testBlogPostsUseBlogPostContentModel() {
		file_put_contents( $this->tmpDir . '/workspace/content/wikitext/10.wiki', 'Blog body' );
		file_put_contents( $this->tmpDir . '/workspace/content/wikitext/20.wiki', 'Page body' );

		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPageIdTargetPageTitleMap' )->willReturn( [
			2 => 'Regular:Page',
		] );
		$dataLookup->method( 'getBlogPostIdTargetBlogPostTitleMap' )->willReturn( [
			1 => 'Blog:32973/Our new tool',
		] );
		$dataLookup->method( 'getSpaceIdForPageId' )->willReturn( 100 );
		$dataLookup->method( 'getSpaceDescriptionRevisionsForSpaceId' )->willReturn( [] );
		$dataLookup->method( 'getSpaceHomepageIdForSpaceId' )->willReturn( -1 );
		$dataLookup->method( 'getBlogPostRevisionsForPageId' )
			->willReturnCallback( static function ( int $pageId ): array {
				if ( $pageId === 1 ) {
					return [ [
						'revision_timestamp' => '20201109160742',
						'body_content_ids' => '[10]',
					] ];
				}

				return [];
			} );
		$dataLookup->method( 'getPageRevisionsForPageId' )
			->willReturnCallback( static function ( int $pageId ): array {
				if ( $pageId === 2 ) {
					return [ [
						'revision_timestamp' => '20201109160743',
						'body_content_ids' => '[20]',
					] ];
				}

				return [];
			} );

		$workspace = $this->createMock( Workspace::class );
		$workspace->method( 'getConvertedContent' )
			->willReturnCallback( static function ( int|string $bodyContentId ): string {
				if ( (string)$bodyContentId === '10' ) {
					return 'Blog body';
				}

				if ( (string)$bodyContentId === '20' ) {
					return 'Page body';
				}

				return '';
			} );

		$migrationConfig = $this->createMock( MigrationConfig::class );
		$migrationConfig->method( 'getComposerPagePerXmlLimit' )->willReturn( 0 );
		$migrationConfig->method( 'getComposerSkipNamespaces' )->willReturn( [] );
		$migrationConfig->method( 'getComposerSkipTitles' )->willReturn( [] );

		$composerDeploymentInfo = new ComposerDeploymentInfo();

		$processor = new Pages(
			new Builder(),
			$dataLookup,
			$workspace,
			$this->makeOutput(),
			$this->tmpDir,
			$migrationConfig,
			$composerDeploymentInfo
		);

		$processor->execute();

		$dom = new DOMDocument();
		$dom->load( $this->tmpDir . '/result/pages.xml' );
		$xpath = new DOMXPath( $dom );

		$blogModel = $xpath->evaluate( 'string(/mediawiki/page[title="Blog:32973/Our new tool"]/revision/model)' );
		$pageModel = $xpath->evaluate( 'string(/mediawiki/page[title="Regular:Page"]/revision/model)' );

		$this->assertSame( 'blog_post', $blogModel );
		$this->assertSame( 'wikitext', $pageModel );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\Pages::addSpaceDescriptionToMainPage
	 */
	public function testAddSpaceDescriptionUsesNewestRevisionNotNewerThanPageRevision() {
		$builder = $this->createMock( Builder::class );
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$workspace = $this->createMock( Workspace::class );
		$migrationConfig = $this->createMock( MigrationConfig::class );

		$migrationConfig->method( 'getComposerPagePerXmlLimit' )
			->willReturn( 0 );

		$workspace->expects( $this->once() )
			->method( 'getConvertedContent' )
			->with( 200 )
			->willReturn( 'Valid description revision' );

		$composerDeploymentInfo = new ComposerDeploymentInfo();

		$processor = new Pages(
			$builder,
			$dataLookup,
			$workspace,
			$this->makeOutput(),
			$this->tmpDir,
			$migrationConfig,
			$composerDeploymentInfo
		);

		$spaceDescriptionRevisions = [
			[
				'revision_timestamp' => '20240101000000',
				'body_content_ids' => json_encode( [ 100 ] ),
			],
			[
				'revision_timestamp' => '20230101000000',
				'body_content_ids' => json_encode( [ 200 ] ),
			],
		];

		$method = new ReflectionMethod( Pages::class, 'addSpaceDescriptionToMainPage' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$processor,
			42,
			42,
			'20231201000000',
			$spaceDescriptionRevisions
		);

		$this->assertStringContainsString( 'Valid description revision', $result );
		$this->assertStringContainsString( 'space-description', $result );
	}

	/** @return Output */
	private function makeOutput(): Output {
		return new class extends Output {
			public function doWrite( string $message, bool $newline ): void {
			}
		};
	}

	/**
	 * @param string $dir
	 * @return void
	 */
	private function deleteDir( string $dir ): void {
		if ( $dir === '' || !is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( $items === false ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}

			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->deleteDir( $path );
				continue;
			}

			unlink( $path );
		}

		rmdir( $dir );
	}
}
