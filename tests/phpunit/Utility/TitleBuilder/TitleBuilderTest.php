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

		$spacePrefixToIdMap = [
			32973 => 'TestNS',
			99999 => 'TestNS_NoMain_Page'
		];

		$spaceIdHomepages = [
			32973 => 32974,
			99999 => -1
		];

		$this->useDefaultMainpage( $spacePrefixToIdMap, $spaceIdHomepages, $helper );
		$this->useCustomMainpage( $spacePrefixToIdMap, $spaceIdHomepages, $helper, 'CustomMainpage' );
	}

	/**
	 * @param array $spacePrefixToIdMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @return void
	 */
	private function useDefaultMainpage( $spacePrefixToIdMap, $spaceIdHomepages, $helper ): void {
		$titleBuilder = new TitleBuilder( $spacePrefixToIdMap, $spaceIdHomepages, $helper );
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
	 * @param array $spacePrefixToIdMap
	 * @param array $spaceIdHomepages
	 * @param XMLHelper $helper
	 * @param string $customMainpage
	 * @return void
	 */
	private function useCustomMainpage( $spacePrefixToIdMap, $spaceIdHomepages, $helper, $customMainpage ): void {
		$titleBuilder = new TitleBuilder( $spacePrefixToIdMap, $spaceIdHomepages, $helper, $customMainpage );
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
