<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\ImagePageLinkHelper;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use PHPUnit\Framework\TestCase;

class ImagePageLinkHelperTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImagePageLinkHelper::getLinkTarget
	 * @return void
	 */
	public function testGetLinkTargetUsesWikiTitleForSameWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'shared-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'shared-wiki', 'DEVOPS', '' );

		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/></ac:link>'
			. '</xml>'
		);

		$linkNode = $dom->getElementsByTagName( 'link' )->item( 0 );
		$helper = new ImagePageLinkHelper( new ConversionDataReader( $workspaceDB ), 42, 'SomePage' );

		$this->assertSame( 'DEVOPS:Page_Title3', $helper->getLinkTarget( $linkNode ) );
		$this->assertFalse( $helper->isBrokenLink() );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\DataReader\ConversionDataReader::getWikiPageTitleForLink
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ImagePageLinkHelper::getLinkTarget
	 * @return void
	 */
	public function testGetLinkTargetUsesInterwikiTitleForDifferentWikiConfiguredSpaces(): void {
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$workspaceDB->addWikisConfig( 'ABC', 'local-wiki', 'ABC', '' );
		$workspaceDB->addWikisConfig( 'DEVOPS', 'foreign-wiki', 'DEVOPS', '' );
		$pageId = $this->findPageId( $workspaceDB, 23, 'Page Title3' );
		$workspaceDB->updatePageInterwikiTitle( $pageId, 'wiki-devops:DEVOPS:Page_Title3' );

		$dom = new DOMDocument();
		$dom->loadXML(
			'<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. '<ac:link><ri:page ri:space-key="DEVOPS" ri:content-title="Page Title3"/></ac:link>'
			. '</xml>'
		);

		$linkNode = $dom->getElementsByTagName( 'link' )->item( 0 );
		$helper = new ImagePageLinkHelper( new ConversionDataReader( $workspaceDB ), 42, 'SomePage' );

		$this->assertSame( 'wiki-devops:DEVOPS:Page_Title3', $helper->getLinkTarget( $linkNode ) );
		$this->assertFalse( $helper->isBrokenLink() );
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
