<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\TitleBuilder;

use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use PHPUnit\Framework\TestCase;

class TitleBuilderTest extends TestCase {

	private array $spaceIdHomepages = [
		32973 => 32974567,
		99999 => -1,
	];

	private array $pageIdParentPageIdMap = [
		229472  => 32974567,
		262231  => 229472,
		999902  => 999901,
	];

	private array $pageIdConfluenceTitleMap = [
		32974567 => 'Dokumentation',
		229472   => 'Roadmap',
		262231   => 'Detailed_planning',
		262211   => 'Roadmap',
		999901   => 'Dokumentation',
		999902   => 'Roadmap',
	];

	/**
	 * Returns a TitleBuilder with a flat namespace prefix (e.g. 'TestNS:').
	 */
	private function makeFlatPrefixBuilder( string $mainpage = 'Main_Page' ): TitleBuilder {
		return new TitleBuilder(
			[
				32973 => 'TestNS:',
				32974 => 'TestNS:',
				99999 => 'TestNS_NoMain_Page:',
			],
			$this->spaceIdHomepages,
			$this->pageIdParentPageIdMap,
			$this->pageIdConfluenceTitleMap,
			$mainpage
		);
	}

	/**
	 * Returns a TitleBuilder with a root-page prefix (e.g. 'TestNS:32973/').
	 */
	private function makeRootPagePrefixBuilder( string $mainpage = 'Main_Page' ): TitleBuilder {
		return new TitleBuilder(
			[
				32973 => 'TestNS:32973/',
				32974 => 'TestNS:32974/',
				99999 => 'TestNS_NoMain_Page:',
			],
			$this->spaceIdHomepages,
			$this->pageIdParentPageIdMap,
			$this->pageIdConfluenceTitleMap,
			$mainpage
		);
	}

	// ---------------------------------------------------------------------------
	// Homepage → default main page name
	// ---------------------------------------------------------------------------

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testHomepageBecomesDefaultMainPage(): void {
		$this->assertSame(
			'TestNS:Main_Page',
			$this->makeFlatPrefixBuilder()->buildTitle( 32973, 32974567, 'Dokumentation' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testHomepageBecomesCustomMainPage(): void {
		$this->assertSame(
			'TestNS:CustomMainpage',
			$this->makeFlatPrefixBuilder( 'CustomMainpage' )->buildTitle( 32973, 32974567, 'Dokumentation' )
		);
	}

	// ---------------------------------------------------------------------------
	// Direct child of homepage (one level deep)
	// ---------------------------------------------------------------------------

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testDirectChildOfHomepage(): void {
		$this->assertSame(
			'TestNS:Roadmap',
			$this->makeFlatPrefixBuilder()->buildTitle( 32973, 229472, 'Roadmap' )
		);
	}

	// ---------------------------------------------------------------------------
	// Grandchild of homepage (two levels deep)
	// ---------------------------------------------------------------------------

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testGrandchildOfHomepage(): void {
		$this->assertSame(
			'TestNS:Roadmap/Detailed_planning',
			$this->makeFlatPrefixBuilder()->buildTitle( 32973, 262231, 'Detailed planning' )
		);
	}

	// ---------------------------------------------------------------------------
	// Space with no homepage (-1): top-level page keeps its own title
	// ---------------------------------------------------------------------------

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testSpaceWithNoHomepageTopLevelPage(): void {
		$this->assertSame(
			'TestNS_NoMain_Page:Dokumentation',
			$this->makeFlatPrefixBuilder()->buildTitle( 99999, 32974567, 'Dokumentation' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testSpaceWithNoHomepageChildPage(): void {
		$this->assertSame(
			'TestNS_NoMain_Page:Dokumentation/Roadmap',
			$this->makeFlatPrefixBuilder()->buildTitle( 99999, 999902, 'Roadmap' )
		);
	}

	// ---------------------------------------------------------------------------
	// Root-page prefix variants
	// ---------------------------------------------------------------------------

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixHomepageDefaultMainPage(): void {
		$this->assertSame(
			'TestNS:32973/Main_Page',
			$this->makeRootPagePrefixBuilder()->buildTitle( 32973, 32974567, 'Dokumentation' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixHomepageCustomMainPage(): void {
		$this->assertSame(
			'TestNS:32973/CustomMainpage',
			$this->makeRootPagePrefixBuilder( 'CustomMainpage' )->buildTitle( 32973, 32974567, 'Dokumentation' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixDirectChild(): void {
		$this->assertSame(
			'TestNS:32973/Roadmap',
			$this->makeRootPagePrefixBuilder()->buildTitle( 32973, 229472, 'Roadmap' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixGrandchild(): void {
		$this->assertSame(
			'TestNS:32973/Roadmap/Detailed_planning',
			$this->makeRootPagePrefixBuilder()->buildTitle( 32973, 262231, 'Detailed planning' )
		);
	}

	/**
	 * Root-page prefix does not affect spaces that already use a flat prefix.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixSpaceWithNoHomepageTopLevel(): void {
		$this->assertSame(
			'TestNS_NoMain_Page:Dokumentation',
			$this->makeRootPagePrefixBuilder()->buildTitle( 99999, 32974567, 'Dokumentation' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testRootPagePrefixSpaceWithNoHomepageChild(): void {
		$this->assertSame(
			'TestNS_NoMain_Page:Dokumentation/Roadmap',
			$this->makeRootPagePrefixBuilder()->buildTitle( 99999, 999902, 'Roadmap' )
		);
	}

	// ---------------------------------------------------------------------------
	// Special-character sanitisation in title segments
	// ---------------------------------------------------------------------------

	/**
	 * Characters that are invalid in MediaWiki titles must be replaced with underscores.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testSpecialCharactersAreSanitised(): void {
		$result = $this->makeFlatPrefixBuilder()->buildTitle( 32973, 229472, 'Road:map%foo?bar' );
		$this->assertSame( 'TestNS:Road_map_foo_bar', $result );
	}

	/**
	 * Multiple consecutive underscores after sanitisation are collapsed into one.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle
	 */
	public function testConsecutiveUnderscoresAreCollapsed(): void {
		$result = $this->makeFlatPrefixBuilder()->buildTitle( 32973, 229472, 'Road  map' );
		// spaces become underscores, consecutive underscores collapse
		$this->assertStringNotContainsString( '__', $result );
	}
}
