<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PageLink;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;
use PHPUnit\Framework\TestCase;

class PageLinkTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PageLink::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42;
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new PageLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$dir/pagelinktest-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * Test PageLink with all spaces on the same wiki (no wikiconfig differentiation)
	 * Should use wiki_title for all links
	 * @return void
	 */
	public function testPageLinkSameWiki() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42; // ABC space
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();

		// Configure all spaces on the same wiki
		$workspaceDB->addWikiConfig( 'ABC', 'production', 'ABC', '' );
		$workspaceDB->addWikiConfig( 'DEVOPS', 'production', 'DEVOPS', '' );
		$workspaceDB->addWikiConfig( 'MKT', 'production', 'MKT', '' );
		$workspaceDB->addWikiConfig( 'DEF', 'production', 'DEF', '' );

		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new PageLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		// When all spaces are on same wiki, all links should use wiki_title format
		$this->assertStringContainsString( '[[ABC:Page_Title]]', $actualOutput, "Same wiki link should use wiki_title format" );
		$this->assertStringContainsString( '[[DEVOPS:SomePage]]', $actualOutput, "Same wiki link should use wiki_title format" );
	}

	/**
	 * Test PageLink with spaces on different wikis (wikiconfig configured)
	 * Should use interwiki_title for cross-wiki links, wiki_title for same-wiki links
	 * @return void
	 */
	public function testPageLinkCrossWiki() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42; // ABC space, assume on wiki 'production'
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();

		// Set up wiki config with different wikis for different spaces
		$workspaceDB->addWikiConfig( 'ABC', 'production', 'ABC', '' );
		$workspaceDB->addWikiConfig( 'DEVOPS', 'staging', 'DEVOPS', '' );
		$workspaceDB->addWikiConfig( 'MKT', 'production', 'MKT', '' );

		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new PageLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		// When spaces are on different wikis, cross-wiki links should use interwiki format
		// ABC and MKT are on 'production' wiki - use wiki_title
		$this->assertStringContainsString( '[[ABC:Page_Title]]', $actualOutput, "Same wiki link should use wiki_title format" );

		// DEVOPS is on 'staging' wiki - use interwiki_title
		$this->assertStringContainsString( '[[wiki-devops:Some_other_page]]', $actualOutput, "Cross-wiki link should use interwiki_title format" );
	}

	/**
	 * Test PageLink without any wikiconfig (default behavior)
	 * When no wikiconfig is set, each space should be treated as a separate wiki
	 * All cross-space links should use interwiki_title format
	 * @return void
	 */
	public function testPageLinkNoWikiConfig() {
		$dir = dirname( __DIR__, 2 ) . '/data';
		$input = file_get_contents( "$dir/pagelinktest-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$currentSpaceId = 42; // ABC space
		$currentRawPagename = 'SomePage';
		$workspaceDB = ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
		// DO NOT set any wiki config - test default behavior

		$dataLookup = new DBConversionDataLookup( $workspaceDB );
		$wikiConfig = new WikiConfig( $workspaceDB );

		$processor = new PageLink(
			$dataLookup,
			$currentSpaceId,
			$currentRawPagename,
			new MigrationConfig( [] ),
			$wikiConfig
		);
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		// Without wikiconfig, all spaces are treated as separate wikis
		// Cross-space links should use interwiki_title format
		$this->assertStringContainsString( '[[wiki-devops:Some_other_page]]', $actualOutput, "Cross-space link without wikiconfig should use interwiki_title format" );
		$this->assertStringContainsString( '[[wiki-def:Page_Title]]', $actualOutput, "Cross-space link without wikiconfig should use interwiki_title format" );
		// Same-space links should still use wiki_title format
		$this->assertStringContainsString( '[[ABC:Page_Title]]', $actualOutput, "Same-space link should use wiki_title format" );
	}
}
