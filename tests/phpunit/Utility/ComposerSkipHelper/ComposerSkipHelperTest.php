<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\ComposerSkipHelperTest;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class ComposerSkipHelperTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipPageById()
	 */
	public function testSkipPageById() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);
		$workspaceDB = $this->getWorkspaceDB();
		$map = $workspaceDB->getPageIdWikiPageTitleMap();
		$map = array_flip( $map );

		// Test a page that is set in the page_invalid_titles database table
		$pageId = null;
		if ( isset( $map['DEVOPS:Page_with_invalid_title'] ) ) {
			$pageId = $map['DEVOPS:Page_with_invalid_title'];
		}
		$this->assertNotNull( $pageId, 'Page id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'Page should be skipped (invalid title)' );

		// Test a page that is set in the body_content_invalids database table
		$pageId = null;
		if ( isset( $map['DEVOPS:Page_with_invalid_content_length'] ) ) {
			$pageId = $map['DEVOPS:Page_with_invalid_content_length'];
		}
		$this->assertNotNull( $pageId, 'Page id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'Page should be skipped (invalid content length)' );

		// Test a page that is not set in the page_invalid_titles database table
		$pageId = null;
		if ( isset( $map['ABC:SomePage'] ) ) {
			$pageId = $map['ABC:SomePage'];
		}
		$this->assertNotNull( $pageId, 'Page id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertFalse( $skip, 'Page should not be skipped' );

		// Test a page that is not set in one of the "invaild" tables but skip by configuration
		$skipHelper = $this->getSkipHelper(
			$this->getMigrationConfig()
		);
		$pageId = null;
		if ( isset( $map['ABC:Some_MediaWiki_page_name'] ) ) {
			$pageId = $map['ABC:Some_MediaWiki_page_name'];
		}
		$this->assertNotNull( $pageId, 'Page id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'Page should be skipped (by configuration)' );

		$pageId = null;
		if ( isset( $map['DEVOPS:Page_Title3'] ) ) {
			$pageId = $map['DEVOPS:Page_Title3'];
		}
		$this->assertNotNull( $pageId, 'Page id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'Page should be skipped (by configuration)' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipPageById()
	 */
	public function testSkipBlogPostById() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);
		$workspaceDB = $this->getWorkspaceDB();
		$map = $workspaceDB->getBlogPostIdWikiBlogPostTitleMap();
		$map = array_flip( $map );

		// Test a page that is set in the page_invalid_titles database table
		$pageId = null;
		if ( isset( $map['Blog:ABC/Some_Blog_Post_with_invalid_title'] ) ) {
			$pageId = $map['Blog:ABC/Some_Blog_Post_with_invalid_title'];
		}
		$this->assertNotNull( $pageId, 'BlogPost id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'BlogPost should be skipped (invalid title)' );

		// Test a page that is set in the body_content_invalids database table
		$pageId = null;
		if ( isset( $map['Blog:DEVOPS/BlogPost_with_invalid_content_length'] ) ) {
			$pageId = $map['Blog:DEVOPS/BlogPost_with_invalid_content_length'];
		}
		$this->assertNotNull( $pageId, 'BlogPost id should not be null' );
		$skip = $skipHelper->skipPageById( $pageId );
		$this->assertTrue( $skip, 'BlogPost should be skipped (invalid content length)' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipWikiTitle()
	 */
	public function testSkipWikiTitle() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);

		// Test a page that is set in the page_invalid_titles database table
		$skip = $skipHelper->skipWikiTitle( 'DEVOPS:Page_with_invalid_title' );
		$this->assertTrue( $skip, 'Page should be skipped (invalid title)' );

		$skip = $skipHelper->skipWikiTitle( 'Blog:ABC/Some_Blog_Post_with_invalid_title' );
		$this->assertTrue( $skip, 'BlogPost should be skipped (invalid title)' );

		// Test a page that is set in the body_content_invalids database table
		$skip = $skipHelper->skipWikiTitle( 'DEVOPS:Page_with_invalid_content_length' );
		$this->assertTrue( $skip, 'Page should be skipped (invalid content length)' );

		$skip = $skipHelper->skipWikiTitle( 'Blog:DEVOPS/BlogPost_with_invalid_content_length' );
		$this->assertTrue( $skip, 'BlogPost should be skipped (invalid content length)' );

		// Test a page that is not set in the page_invalid_titles database table
		$skip = $skipHelper->skipWikiTitle( 'ABC:SomePage' );
		$this->assertFalse( $skip, 'Page should not be skipped' );
	}

	/**
	 * @return ComposerSkipHelper
	 */
	private function getSkipHelper( MigrationConfig $migrationConfig ): ComposerSkipHelper {
		return new ComposerSkipHelper(
			$this->getComposerDataLookup(),
			$migrationConfig
		);
	}

	/**
	 * @return DBComposerDataLookup
	 */
	private function getComposerDataLookup(): DBComposerDataLookup {
		return new DBComposerDataLookup(
			$this->getWorkspaceDB()
		);
	}

	private function getEmptyMigrationConfig(): MigrationConfig {
		return new MigrationConfig( [] );
	}

	private function getMigrationConfig(): MigrationConfig {
		return new MigrationConfig( [
			'composer-skip-namespace' => [
				'DEVOPS'
			],
			'composer-skip-titles' => [
				'ABC:Some_MediaWiki_page_name'
			]
		] );
	}

	/**
	 * @return WorkspaceDB
	 */
	private function getWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat();
	}
}
