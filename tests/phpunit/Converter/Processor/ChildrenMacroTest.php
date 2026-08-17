<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

class ChildrenMacroTest extends StructuredMacroProcessorTestBase {
	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		$dataReader = new ConverterDirectDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		return new ChildrenMacro( 42, 'ABC:Some_page', $dataReader );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro::process
	 * @return void
	 */
	public function testProcessUsesWikiTitleForSameWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );

		$dom = new \DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<ac:structured-macro ac:name="children">'
			. '<ac:parameter ac:name="page"><ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/>'
			. '</ac:link></ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new ChildrenMacro( 42, 'ABC:Some_page', new ConverterDirectDataReader( $workspaceDB ) );
		$processor->process( $dom );

		$this->assertSame( '{{SubpageList|page=DEVOPS:Page Title3}}', trim( $dom->documentElement->textContent ) );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro::process
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
			. '<ac:structured-macro ac:name="children">'
			. '<ac:parameter ac:name="page"><ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/>'
			. '</ac:link></ac:parameter></ac:structured-macro></xml>'
		);

		$processor = new ChildrenMacro( 42, 'ABC:Some_page', new ConverterDirectDataReader( $workspaceDB ) );
		$processor->process( $dom );

		$this->assertSame(
			'{{SubpageList|page=wiki-devops:DEVOPS:Page Title3}}',
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
