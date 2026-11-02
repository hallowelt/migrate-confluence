<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\Output;

class InvalidContentsTest extends TestCase {

	/** @var array[] */
	private array $revisions = [];

	/** @var string */
	private string $tmpDir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = dirname( __DIR__, 4 ) . '/.tmp/invalid-contents-test-' . uniqid( '', true );
		mkdir( $this->tmpDir, 0755, true );
	}

	protected function tearDown(): void {
		foreach ( glob( $this->tmpDir . '/result/*' ) ?: [] as $file ) {
			unlink( $file );
		}
		if ( is_dir( $this->tmpDir . '/result' ) ) {
			rmdir( $this->tmpDir . '/result' );
		}
		rmdir( $this->tmpDir );
		parent::tearDown();
	}

	/**
	 * @return InvalidContents
	 */
	private function makeProcessor( DBComposerDataLookup $dataLookup, Workspace $workspace ): InvalidContents {
		$this->revisions = [];
		$builder = $this->createMock( Builder::class );
		$builder->method( 'addRevision' )->willReturnCallback(
			function ( string $title, string $text, string $timestamp, $u = '', $m = '', $f = '', $slot = [] ) {
				$this->revisions[] = [ 'title' => $title, 'text' => $text, 'timestamp' => $timestamp, 'slot' => $slot ];
			}
		);

		$migrationConfig = $this->createMock( MigrationConfig::class );
		$migrationConfig->method( 'getComposerPagePerXmlLimit' )->willReturn( 0 );
		$migrationConfig->method( 'getNsTalkPrefix' )->willReturn( 'Talk' );

		$output = new class extends Output {
			/** @inheritDoc */
			public function doWrite( string $message, bool $newline ): void {
			}
		};

		return new InvalidContents( $builder, $dataLookup, $workspace, $output, $this->tmpDir, $migrationConfig );
	}

	/**
	 * @param array $returns Return values by method name; unlisted lookups return an empty array
	 * @return DBComposerDataLookup
	 */
	private function makeDataLookup( array $returns ): DBComposerDataLookup {
		$returns += [
			'getInvalidPages' => [],
			'getInvalidBlogPosts' => [],
			'getInvalidPageTemplates' => [],
			'getCommentsForPages' => [],
			'getCommentsForBlogPosts' => [],
			'getUsers' => [],
			'getPageRevisionsForPageId' => [],
		];
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		foreach ( $returns as $method => $value ) {
			if ( is_callable( $value ) ) {
				$dataLookup->method( $method )->willReturnCallback( $value );
			} else {
				$dataLookup->method( $method )->willReturn( $value );
			}
		}
		return $dataLookup;
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents::execute
	 */
	public function testInvalidPageWithoutWikiTitleIsWrittenUnderPlaceholderTitle(): void {
		$dataLookup = $this->makeDataLookup( [
			'getInvalidPages' => [
				[ 'page_id' => 7, 'wiki_title' => '' ],
				[ 'page_id' => 8, 'wiki_title' => 'TEST:Named' ],
			],
			'getPageRevisionsForPageId' => static fn ( int $id ) => [ [
				'revision_timestamp' => '20240101000000',
				'body_content_ids' => json_encode( [ $id ] ),
			] ],
		] );
		$workspace = $this->createMock( Workspace::class );
		$workspace->method( 'getConvertedContent' )->willReturnCallback( static fn ( $id ) => "content $id" );

		$this->makeProcessor( $dataLookup, $workspace )->execute();

		$titles = array_column( $this->revisions, 'title' );
		$this->assertSame( [ 'Invalid_contents/Page_7', 'TEST:Named' ], $titles );
		$this->assertSame( "content 7\n", $this->revisions[0]['text'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents::execute
	 */
	public function testRevisionWithCorruptContentIdsIsSkippedButSiblingIsWritten(): void {
		$dataLookup = $this->makeDataLookup( [
			'getInvalidPages' => [ [ 'page_id' => 7, 'wiki_title' => 'TEST:Page' ] ],
			'getPageRevisionsForPageId' => [
				[ 'revision_timestamp' => '20240102000000', 'body_content_ids' => 'not json' ],
				[ 'revision_timestamp' => '20240101000000', 'body_content_ids' => '[1]' ],
			],
		] );
		$workspace = $this->createMock( Workspace::class );
		$workspace->method( 'getConvertedContent' )->willReturn( 'content' );

		$this->makeProcessor( $dataLookup, $workspace )->execute();

		$this->assertCount( 1, $this->revisions );
		$this->assertSame( '20240101000000', $this->revisions[0]['timestamp'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents::execute
	 */
	public function testCommentsOfPageWithoutWikiTitleLandOnPlaceholderTalkPage(): void {
		$dataLookup = $this->makeDataLookup( [
			'getInvalidPages' => [ [ 'page_id' => 7, 'wiki_title' => '' ] ],
			'getCommentsForPages' => [
			[
				'container_id' => 7,
				'content_class' => 'Page',
				'wiki_title' => '',
				'body_content_ids' => '[5]',
				'user_key' => 'u1',
			],
			[
				'container_id' => 7,
				'content_class' => 'Page',
				'wiki_title' => '',
				'body_content_ids' => 'not json',
				'user_key' => 'u1',
			],
			[
				'container_id' => 999,
				'content_class' => 'Page',
				'wiki_title' => 'TEST:Other',
				'body_content_ids' => '[5]',
				'user_key' => 'u1',
			],
			],
		] );
		$workspace = $this->createMock( Workspace::class );
		$workspace->method( 'getConvertedContent' )->willReturn( 'a comment' );

		$this->makeProcessor( $dataLookup, $workspace )->execute();

		$this->assertCount( 1, $this->revisions );
		$this->assertSame( 'Talk:Invalid_contents/Page_7', $this->revisions[0]['title'] );
		$this->assertSame( 'cs-comments', $this->revisions[0]['slot']['role'] );
		$comments = json_decode( $this->revisions[0]['slot']['text'], true );
		$this->assertCount( 1, $comments );
		$this->assertSame( 'a comment', $comments[1]['wikitext'] );
	}
}
