<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\TitleBuilder;

use DOMDocument;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use PHPUnit\Framework\TestCase;

class TitleBuilderTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle()
	 */
	public function testBuildTitle() {
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/entities_test.xml' );
		$helper = new XMLHelper( $dom );

		$spaceIdHomepages = [
			32973 => 32974567,
			99999 => -1
		];

		$spaceIdPrefixMap = [
			32973 => 'TestNS:',
			32974 => 'TestNS:',
			99999 => 'TestNS_NoMain_Page:'
		];

		$pageIdParentPageIdMap = [
			229472 => 32974567,
			262231 => 229472,
			999902 => 999901
		];

		$pageIdConfluenceTitleMap = [
			999902 => 'Roadmap',
			999901 => 'Dokumentation',
			262231 => 'Detailed_planning',
			229472 => 'Roadmap',
			262211 => 'Roadmap',
			32974567 => 'Dokumentation'
		];

		$this->useDefaultMainpage(
			$spaceIdPrefixMap, $spaceIdHomepages, $helper, $pageIdParentPageIdMap,
			$pageIdConfluenceTitleMap );
		$this->useCustomMainpage(
			$spaceIdPrefixMap, $spaceIdHomepages, $helper, 'CustomMainpage',
			$pageIdParentPageIdMap, $pageIdConfluenceTitleMap );

		$spaceIdPrefixMap = [
			32973 => 'TestNS:32973/',
			32974 => 'TestNS:32974/',
			99999 => 'TestNS_NoMain_Page:'
		];

		$this->useDefaultMainpageWithRootPage(
			$spaceIdPrefixMap, $spaceIdHomepages, $helper, $pageIdParentPageIdMap,
			$pageIdConfluenceTitleMap );
		$this->useCustomMainpageWithRootPage(
			$spaceIdPrefixMap, $spaceIdHomepages, $helper, 'CustomMainpage',
			$pageIdParentPageIdMap, $pageIdConfluenceTitleMap );
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @return void
	 */
	private function useDefaultMainpage(
		$spaceIdPrefixMap, $spaceIdHomepages, $helper, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap ): void {
		$titleBuilder = new TitleBuilder(
			$spaceIdPrefixMap, $spaceIdHomepages, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap, $helper );
		$actualTitles = $this->buildTitles(
			$titleBuilder, $helper );

		$expectedTitles = [
			"TestNS:Main_Page",
			"TestNS:Roadmap",
			"TestNS:Roadmap/Detailed_planning",
			"TestNS_NoMain_Page:Dokumentation",
			"TestNS_NoMain_Page:Dokumentation/Roadmap",
		];

		$this->assertEquals( $expectedTitles, $actualTitles );
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @param string $customMainpage
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @return void
	 */
	private function useCustomMainpage(
		$spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage, $pageIdParentPageIdMap,
		$pageIdConfluenceTitleMap ): void {
		$titleBuilder = new TitleBuilder(
			$spaceIdPrefixMap, $spaceIdHomepages, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap,
			$helper, $customMainpage );
		$actualTitles = $this->buildTitles( $titleBuilder, $helper );

		$expectedTitles = [
			"TestNS:$customMainpage",
			"TestNS:Roadmap",
			"TestNS:Roadmap/Detailed_planning",
			"TestNS_NoMain_Page:Dokumentation",
			"TestNS_NoMain_Page:Dokumentation/Roadmap",
		];

		$this->assertEquals( $expectedTitles, $actualTitles );
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @return void
	 */
	private function useDefaultMainpageWithRootPage(
		$spaceIdPrefixMap, $spaceIdHomepages, $helper, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap ): void {
		$titleBuilder = new TitleBuilder(
			$spaceIdPrefixMap, $spaceIdHomepages, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap, $helper );
		$actualTitles = $this->buildTitles( $titleBuilder, $helper );

		$expectedTitles = [
			"TestNS:32973/Main_Page",
			"TestNS:32973/Roadmap",
			"TestNS:32973/Roadmap/Detailed_planning",
			"TestNS_NoMain_Page:Dokumentation",
			"TestNS_NoMain_Page:Dokumentation/Roadmap",
		];

		$this->assertEquals( $expectedTitles, $actualTitles );
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @param string $customMainpage
	 * @param array $pageIdParentPageIdMap
	 * @param array $pageIdConfluenceTitleMap
	 * @return void
	 */
	private function useCustomMainpageWithRootPage(
		$spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage, $pageIdParentPageIdMap,
		$pageIdConfluenceTitleMap
	): void {
		$titleBuilder = new TitleBuilder(
			$spaceIdPrefixMap, $spaceIdHomepages, $pageIdParentPageIdMap, $pageIdConfluenceTitleMap,
			$helper, $customMainpage );
		$actualTitles = $this->buildTitles( $titleBuilder, $helper );

		$expectedTitles = [
			"TestNS:32973/$customMainpage",
			"TestNS:32973/Roadmap",
			"TestNS:32973/Roadmap/Detailed_planning",
			"TestNS_NoMain_Page:Dokumentation",
			"TestNS_NoMain_Page:Dokumentation/Roadmap",
		];

		$this->assertEquals( $expectedTitles, $actualTitles );
	}

	/**
	 * @param TitleBuilder $titleBuilder
	 * @param XMLHelper $helper
	 * @return array
	 */
	private function buildTitles( $titleBuilder, $helper ): array {
		$pageNodes = $helper->getObjectNodes( "Page" );

		$actualTitles = [];
		foreach ( $pageNodes as $pageNode ) {
			$fullTitle = $titleBuilder->buildTitle( $pageNode );

			$originalVersionID = $helper->getPropertyValue( 'originalVersion', $pageNode );
			if ( $originalVersionID !== null ) {
				continue;
			}

			$actualTitles[] = $fullTitle;
		}
		return $actualTitles;
	}
}
