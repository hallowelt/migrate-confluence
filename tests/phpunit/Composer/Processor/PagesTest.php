<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
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
		$skipHelper = new ComposerSkipHelper( $dataLookup, $migrationConfig );

		$processor = new Pages(
			$builder,
			$dataLookup,
			$workspace,
			$this->makeOutput(),
			$this->tmpDir,
			$migrationConfig,
			$composerDeploymentInfo,
			$skipHelper
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
