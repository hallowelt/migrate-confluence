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
			32973 => 32974,
			99999 => -1
		];

		$spaceIdPrefixMap = [
			32973 => 'TestNS:',
			32974 => 'TestNS:',
			99999 => 'TestNS_NoMain_Page:'
		];

		$this->useDefaultMainpage( $spaceIdPrefixMap, $spaceIdHomepages, $helper );
		$this->useCustomMainpage( $spaceIdPrefixMap, $spaceIdHomepages, $helper, 'CustomMainpage' );

		$spaceIdPrefixMap = [
			32973 => 'TestNS:32973/',
			32974 => 'TestNS:32974/',
			99999 => 'TestNS_NoMain_Page:'
		];

		$this->useDefaultMainpageWithRootPage( $spaceIdPrefixMap, $spaceIdHomepages, $helper );
		$this->useCustomMainpageWithRootPage( $spaceIdPrefixMap, $spaceIdHomepages, $helper, 'CustomMainpage' );
	}

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @return void
	 */
	private function useDefaultMainpage( $spaceIdPrefixMap, $spaceIdHomepages, $helper ): void {
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $spaceIdHomepages, $helper );
		$actualTitles = $this->buildTitles( $titleBuilder, $helper );

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
	 * @return void
	 */
	private function useCustomMainpage( $spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage ): void {
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage );
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
	 * @return void
	 */
	private function useDefaultMainpageWithRootPage( $spaceIdPrefixMap, $spaceIdHomepages, $helper ): void {
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $spaceIdHomepages, $helper );
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
	 * @return void
	 */
	private function useCustomMainpageWithRootPage( $spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage ): void {
		$titleBuilder = new TitleBuilder( $spaceIdPrefixMap, $spaceIdHomepages, $helper, $customMainpage );
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
