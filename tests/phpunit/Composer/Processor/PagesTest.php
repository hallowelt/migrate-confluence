<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use DOMDocument;
use DOMXPath;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
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

		$buckets = new DataBuckets( [
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-body-content-id-to-space-description-id-map',
			'global-body-content-id-to-page-id-map',
			'global-title-revisions',
		] );
		$buckets->setBucketData( 'global-space-id-homepages', [] );
		$buckets->setBucketData( 'global-space-id-to-description-id-map', [] );
		$buckets->setBucketData( 'global-body-content-id-to-space-description-id-map', [] );
		$buckets->setBucketData( 'global-body-content-id-to-page-id-map', [] );
		$buckets->setBucketData( 'global-title-revisions', [
			'Blog:32973/Our new tool' => [ '10@1-20201109160742' ],
			'Regular:Page' => [ '20@1-20201109160743' ],
		] );

		$processor = new Pages(
			new Builder(),
			$buckets,
			new Workspace( new SplFileInfo( $this->tmpDir . '/workspace' ) ),
			$this->makeOutput(),
			$this->tmpDir,
			[]
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
