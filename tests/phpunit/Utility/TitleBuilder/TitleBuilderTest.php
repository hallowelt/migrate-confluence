<?php


namespace HalloWelt\MigrateConfluence\Tests\Utility\TitleBuilder;


use DOMDocument;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use PHPUnit\Framework\TestCase;

class TitleBuilderTest extends TestCase
{
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\TitleBuilder::buildTitle()
	 */
	public function testBuildTitle()
	{
		$dom = new DOMDocument();
		$dom->load( __DIR__ . '/entities_test.xml' );
		$helper = new XMLHelper( $dom );

		$spacePrefixToIdMap = [
			32973 => 'TestNS'
		];

		$titleBuilder = new TitleBuilder( $spacePrefixToIdMap, $helper );
		$pageNodes = $helper->getObjectNodes( "Page" );

		$actualTitles = [];
		foreach( $pageNodes as $pageNode ) {
			$fullTitle = $titleBuilder->buildTitle( $pageNode );

			$originalVersionID = $helper->getPropertyValue( 'originalVersion', $pageNode );
			if( $originalVersionID !== null ) {
				continue;
			}

			$actualTitles[] = $fullTitle;
		}

		$expectedTitles = [
			"TestNS:Dokumentation",
			"TestNS:Dokumentation/Roadmap",
			"TestNS:Dokumentation/Roadmap/Detailed_planning"
		];

		$this->assertEquals( $expectedTitles, $actualTitles );
	}

}