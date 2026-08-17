<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\MacroChainTest;

use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use ReflectionClass;

/**
 * @group full
 */
class ExcerptIncludeMacroChainTest extends MacroChainTestBase {

	protected function setUp(): void {
		$workspaceDb = $this->createWorkspaceDb();
		$this->dataReader = new ConverterDirectDataReader( $workspaceDb );
	}

	/**
	 * Build a real WorkspaceDB (backed by an in-memory SQLite db) with two pages
	 * covering both excerpt name fallback cases in RestoreExcerptIncludeMacro:
	 * - "Some Confluence page name" has a body with only unnamed excerpt macros,
	 *   so no name can be found and the default fallback ("excerpt-0") is used.
	 * - "Another Confluence page name" has a body with a named excerpt macro,
	 *   so that name ("Second Excerpt") is used as the fallback.
	 *
	 * @return WorkspaceDB
	 */
	private function createWorkspaceDb(): WorkspaceDB {
		$reflection = new ReflectionClass( WorkspaceDB::class );
		$workspaceDb = $reflection->newInstanceWithoutConstructor();

		$dbProp = $reflection->getProperty( 'db' );
		$dbProp->setValue( $workspaceDb, new \SQLite3( ':memory:', SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE ) );

		$reflection->getMethod( 'createTables' )->invoke( $workspaceDb );

		$workspaceDb->addSpace( 42, 'ABC', 'Some space', 'ABC', '', '', -1, -1 );

		$this->addPageWithoutNamedExcerpt( $workspaceDb );
		$this->addPageWithNamedExcerpt( $workspaceDb );

		return $workspaceDb;
	}

	/**
	 * Page whose body only contains excerpt macros without an explicit name, so
	 * the excerpt name fallback logic can't find a name and falls back to the
	 * default "excerpt-0".
	 *
	 * @param WorkspaceDB $workspaceDb
	 * @return void
	 */
	private function addPageWithoutNamedExcerpt( WorkspaceDB $workspaceDb ): void {
		$pageId = 10000;
		$bodyContentId = 80000;

		$workspaceDb->addBodyContent( $bodyContentId, $pageId, 'Page', [] );
		$workspaceDb->addBodyContentBody(
			$bodyContentId,
			'<ac:structured-macro ac:name="excerpt"><ac:rich-text-body>' .
				'<p>Some excerpt content</p>' .
			'</ac:rich-text-body></ac:structured-macro>' .
			'<ac:structured-macro ac:name="excerpt"><ac:rich-text-body>' .
				'<p>Some other excerpt content</p>' .
			'</ac:rich-text-body></ac:structured-macro>'
		);

		$workspaceDb->addPage(
			$pageId,
			42,
			'Some Confluence page name',
			'ABC:Some_MediaWiki_page_name',
			'current',
			'20240101000000',
			'',
			'1',
			-1,
			-1,
			[ $bodyContentId ],
			[],
			[],
			[]
		);
	}

	/**
	 * Page whose body contains a named excerpt macro, so the excerpt name
	 * fallback logic finds and uses that name.
	 *
	 * @param WorkspaceDB $workspaceDb
	 * @return void
	 */
	private function addPageWithNamedExcerpt( WorkspaceDB $workspaceDb ): void {
		$pageId = 10001;
		$bodyContentId = 80001;

		$workspaceDb->addBodyContent( $bodyContentId, $pageId, 'Page', [] );
		$workspaceDb->addBodyContentBody(
			$bodyContentId,
			'<ac:structured-macro ac:name="excerpt">' .
				'<ac:parameter ac:name="name">Second Excerpt</ac:parameter>' .
				'<ac:rich-text-body>' .
					'<p>Some other excerpt content</p>' .
				'</ac:rich-text-body>' .
			'</ac:structured-macro>'
		);

		$workspaceDb->addPage(
			$pageId,
			42,
			'Another Confluence page name',
			'ABC:Another_wiki_page',
			'current',
			'20240101000000',
			'',
			'1',
			-1,
			-1,
			[ $bodyContentId ],
			[],
			[],
			[]
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ExcerptIncludeMacro::process
	 * @return void
	 */
	public function testMacroChain(): void {
		$dir = dirname( __DIR__, 2 ) . '/data/PageExcerpt';
		$fixtures = [
			'excerpt-include-macro-input.xml' => 'excerpt-include-macro-output.wikitext',
			'excerpt-include-macro-fallback-found-input.xml' => 'excerpt-include-macro-fallback-found-output.wikitext',
		];

		foreach ( $fixtures as $inputFixture => $expectedFixture ) {
			$inputPath = "$dir/$inputFixture";
			$expectedPath = "$dir/$expectedFixture";
			$this->assertFileExists( $inputPath, "Missing input fixture $inputFixture" );
			$this->assertFileExists( $expectedPath, "Missing expected fixture $expectedFixture" );
			$inputXml = (string)file_get_contents( $inputPath );
			$expected = $this->applyConfluenceFinalReplacements( (string)file_get_contents( $expectedPath ) );
			$actual = $this->runChainWithProcessor( new ExcerptIncludeMacro( $this->dataReader, 42 ), $inputXml );
			$this->assertSame( $expected, $actual, "Mismatch for fixture $inputFixture" );
		}
	}

}
