<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

class PageTreeMacroTest extends ProcessorTestCase {
	/** @var ConversionDataReader */
	private $dataLookup;

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dataLookup = $this->makeLookup();
		$this->doTest( 'pagetree-macro-input.xml', 'pagetree-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcessWithoutSpaceKey() {
		$this->dataLookup = $this->makeLookup();
		$this->doTest( 'pagetree-macro-no-spacekey-input.xml', 'pagetree-macro-no-spacekey-output.xml' );
	}

	/**
	 * @return ConversionDataReader
	 */
	private function makeLookup() {
		return new ConversionDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
	}

	/**
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( string $input, string $output ) {
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $input );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . '/data/' . $output );
		$processor = new PageTreeMacro( $this->dataLookup, 42, 'Testpage', 'ABC:SomeLinkedPage/Testpage', 'Main Page' );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();
		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcessUsesWikiTitleForSameWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );

		$dom = new \DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/>'
			. '</ac:link></ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new PageTreeMacro(
			new ConversionDataReader( $workspaceDB ),
			42,
			'Testpage',
			'ABC:SomeLinkedPage/Testpage'
		);
		$processor->process( $dom );

		$this->assertSame(
			'{{PageTree|content-title=DEVOPS:Page_Title3|space-key=DEVOPS}}',
			trim( $dom->documentElement->textContent )
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageTreeMacro::process
	 * @return void
	 */
	public function testProcessUsesInterwikiTitleForDifferentWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'local-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'foreign-wiki', 'DEVOPS', '' );
		$pageId = $this->findPageId( $workspaceDB, 23, 'Page Title3' );
		$workspaceDB->updatePageInterwikiTitle( $pageId, 'wiki-devops:DEVOPS:Page_Title3' );

		$dom = new \DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<ac:structured-macro ac:name="pagetree">'
			. '<ac:parameter ac:name="root"><ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/>'
			. '</ac:link></ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new PageTreeMacro(
			new ConversionDataReader( $workspaceDB ),
			42,
			'Testpage',
			'ABC:SomeLinkedPage/Testpage'
		);
		$processor->process( $dom );

		$this->assertSame(
			'{{PageTree|content-title=wiki-devops:DEVOPS:Page_Title3|space-key=DEVOPS}}',
			trim( $dom->documentElement->textContent )
		);
	}

	/**
	 * @param \HalloWelt\MigrateConfluence\Database\WorkspaceDB $workspaceDB
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return int
	 */
	private function findPageId( $workspaceDB, int $spaceId, string $confluenceTitle ): int {
		foreach ( $workspaceDB->getPages() as $page ) {
			if ( (int)$page['space_id'] === $spaceId && $page['confluence_title'] === $confluenceTitle ) {
				return (int)$page['page_id'];
			}
		}

		$this->fail( "Could not find page '$confluenceTitle' in space $spaceId" );
	}
}
