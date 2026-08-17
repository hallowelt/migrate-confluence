<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class PageLinkTest extends ProcessorTestCase {
		/**
		 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::process
		 * @return void
		 */
	public function testProcess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$dataReader = new ConverterDirectDataReader( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );

		$processor = new PageLink(
			$dataReader,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/pagelinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::process
	 * @return void
	 */
	public function testProcessUsesWikiTitleForSameWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );

		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<div><ac:link><ri:page ri:content-title="Page Title3" ri:space-key="DEVOPS"/>'
			. '<ac:plain-text-link-body><![CDATA[Same wiki link]]></ac:plain-text-link-body>'
			. '</ac:link></div></xml>'
		);

		$processor = new PageLink(
			new ConverterDirectDataReader( $workspaceDB ),
			42,
			'SomePage',
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actual = trim( $dom->getElementsByTagName( 'div' )->item( 0 )->textContent );
		$this->assertSame( '[[DEVOPS:Page_Title3|Same wiki link]]', $actual );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::process
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
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<div><ac:link><ri:page ri:content-title="Page Title3" ri:space-key="DEVOPS"/>'
			. '<ac:plain-text-link-body><![CDATA[Cross wiki link]]></ac:plain-text-link-body>'
			. '</ac:link></div></xml>'
		);

		$processor = new PageLink(
			new ConverterDirectDataReader( $workspaceDB ),
			42,
			'SomePage',
			new MigrationConfig( [] )
		);
		$processor->process( $dom );

		$actual = trim( $dom->getElementsByTagName( 'div' )->item( 0 )->textContent );
		$this->assertSame( '[[wiki-devops:DEVOPS:Page_Title3|Cross wiki link]]', $actual );
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
