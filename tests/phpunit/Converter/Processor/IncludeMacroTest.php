<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

class IncludeMacroTest extends ProcessorTestCase {
	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/include-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/include-macro-output.xml' );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro::preprocess
	 * @return void
	 */
	public function testProcess() {
		$dataLookup = new ConversionDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$currentSpaceId = 42;

		$input = $this->getInput();
		$expectedOutput = $this->getExpectedOutput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new IncludeMacro( $dataLookup, $currentSpaceId );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro::process
	 * @return void
	 */
	public function testProcessUsesWikiTitleForSameWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );

		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_namespace2">'
			. '<ac:structured-macro ac:name="include"><ac:parameter ac:name="">'
			. '<ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/></ac:link>'
			. '</ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new IncludeMacro( new ConversionDataReader( $workspaceDB ), 42 );
		$processor->process( $dom );

		$this->assertSame( '{{:DEVOPS:Page_Title3}}', trim( $dom->documentElement->textContent ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncludeMacro::process
	 * @return void
	 */
	public function testProcessUsesInterwikiTitleForDifferentWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'local-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'foreign-wiki', 'DEVOPS', '' );
		$pageId = $this->findPageId( $workspaceDB, 23, 'Page Title3' );
		$workspaceDB->updatePageInterwikiTitle( $pageId, 'wiki-devops:DEVOPS:Page_Title3' );

		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_namespace2">'
			. '<ac:structured-macro ac:name="include"><ac:parameter ac:name="">'
			. '<ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/></ac:link>'
			. '</ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new IncludeMacro( new ConversionDataReader( $workspaceDB ), 42 );
		$processor->process( $dom );

		$this->assertSame( '{{:wiki-devops:DEVOPS:Page_Title3}}', trim( $dom->documentElement->textContent ) );
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
