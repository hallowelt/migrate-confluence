<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class FilesTest extends TestCase {

	/** @var string */
	private $tmpDir = '';

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/migrate-confluence-files-test-' . uniqid( '', true );
		mkdir( $this->tmpDir, 0755, true );
	}

	protected function tearDown(): void {
		$this->deleteDir( $this->tmpDir );
		parent::tearDown();
	}

	/**
	 * Regression test: attachments must actually be counted towards numOfRevisions so
	 * that files.xml gets written. A previous change called $this->builder->addFileRevision()
	 * directly instead of the counting wrapper $this->addFileRevision(), which left
	 * numOfRevisions at 0 forever and silently skipped writing files.xml, even though the
	 * attachment file itself was uploaded.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\Files::execute
	 */
	public function testExecuteWritesFilesXmlForAdditionalAttachment(): void {
		$sourceFile = $this->tmpDir . '/source.txt';
		file_put_contents( $sourceFile, 'attachment content' );

		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPageAttachments' )->willReturn( [] );
		$dataLookup->method( 'getBlogPostAttachments' )->willReturn( [] );
		$dataLookup->method( 'getRoadmapSvgs' )->willReturn( [] );
		$dataLookup->method( 'getAdditionalAttachments' )->willReturn( [
			[ 'attachment_id' => 1, 'target_attachment_filename' => 'CON:SomeFile.txt' ],
		] );
		$dataLookup->method( 'isPageInvalid' )->willReturn( false );
		$dataLookup->method( 'isAttachmentInvalid' )->willReturn( false );
		$dataLookup->method( 'getAttachmentDescription' )->willReturn( '' );
		$dataLookup->method( 'getAttachmentRevisionsForAttachmentId' )->willReturn( [
			[
				'attachment_reference' => $sourceFile,
				'revision_timestamp' => '20240101000000',
				'file_extension' => 'txt',
			],
		] );

		$migrationConfig = new MigrationConfig( [] );
		$skipHelper = new ComposerSkipHelper( $dataLookup, $migrationConfig );
		$deploymentInfo = new ComposerDeploymentInfo();
		$workspace = new Workspace( new SplFileInfo( $this->tmpDir ) );

		$processor = new Files(
			$dataLookup,
			$workspace,
			$this->makeOutput(),
			$this->tmpDir,
			$migrationConfig,
			$deploymentInfo,
			$skipHelper
		);
		$processor->setSubDir( 'CON' );
		// Deliberately not calling setCurrentSpaceIds(): leaving currentSpaceIds null makes
		// Files pull attachments via the no-arg dataLookup methods stubbed above.

		$processor->execute();

		$filesXmlPath = $this->tmpDir . '/result/CON/files.xml';
		$this->assertFileExists(
			$filesXmlPath,
			'files.xml must be written whenever attachments were processed'
		);

		$xml = file_get_contents( $filesXmlPath );
		$this->assertStringContainsString( 'CON:SomeFile.txt', $xml );
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
