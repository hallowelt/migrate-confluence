<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

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
		$method->setAccessible( true );
		$processor = ( new ReflectionClass( DefaultPages::class ) )->newInstanceWithoutConstructor();

		$this->assertSame(
			$expected,
			$method->invoke( $processor, $fileObj, $basepath, $directoriesWithWikitext )
		);
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
