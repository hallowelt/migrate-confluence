<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class DefaultPagesTest extends TestCase {

	/**
	 * @param string $relativeFilePath
	 * @param array $directoriesWithWikitext
	 * @param array $expected
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages::getDefaultPageForFile
	 * @dataProvider provideGetDefaultPageForFileData
	 * @return void
	 */
	public function testGetDefaultPageForFile(
		string $relativeFilePath,
		array $directoriesWithWikitext,
		array $expected
	): void {
		$basepath = '/tmp/migrate-confluence-default-pages-test/';
		$fileObj = new SplFileInfo( $basepath . $relativeFilePath );

		$method = new ReflectionMethod( DefaultPages::class, 'getDefaultPageForFile' );
		$processor = ( new ReflectionClass( DefaultPages::class ) )->newInstanceWithoutConstructor();

		$this->assertSame(
			$expected,
			$method->invoke( $processor, $fileObj, $basepath, $directoriesWithWikitext )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages::execute
	 */
	public function testAddsFolderAndStylesheetWhenFolderIsRegistered(): void {
		$builder = $this->createMock( Builder::class );
		$addedPages = [];
		$builder->method( 'addRevision' )
			->willReturnCallback( static function ( string $pageName ) use ( &$addedPages ): void {
				$addedPages[] = $pageName;
			} );
		$builder->expects( $this->once() )->method( 'buildAndSave' );

		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getRegisteredDefaultPagesForSpaceId' )->willReturn( [
			'Template' => [ 'Folder' ],
		] );

		$processor = new DefaultPages(
			$builder,
			$this->createMock( Output::class ),
			sys_get_temp_dir(),
			new MigrationConfig( [] ),
			$dataLookup
		);
		$processor->setCurrentSpaceIds( [ 1 ] );

		$processor->execute();

		$this->assertContains( 'Template:Folder', $addedPages );
		$this->assertContains( 'Template:Folder/Style.css', $addedPages );
	}

	/**
	 * @return array
	 */
	public function provideGetDefaultPageForFileData(): array {
		$basepath = '/tmp/migrate-confluence-default-pages-test/';

		return [
			'direct namespace file' => [
				'Template/TM',
				[],
				[
					'namespace' => 'Template',
					'registered_name' => 'TM',
					'wiki_title' => 'Template:TM',
				],
			],
			'wikitext body file' => [
				'Template/folder/wikitext',
				[ $basepath . 'Template/folder' => true ],
				[
					'namespace' => 'Template',
					'registered_name' => 'folder',
					'wiki_title' => 'Template:Folder',
				],
			],
			'sibling subpage file' => [
				'Template/folder/style.css',
				[ $basepath . 'Template/folder' => true ],
				[
					'namespace' => 'Template',
					'registered_name' => 'folder',
					'wiki_title' => 'Template:Folder/Style.css',
				],
			],
		];
	}
}
