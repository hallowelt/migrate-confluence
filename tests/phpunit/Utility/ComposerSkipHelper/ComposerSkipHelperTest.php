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
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipPage()
	 */
	public function testSkipPageById() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);

		// Test a page that is set in the page_invalid_titles database table
		$skip = $skipHelper->skipPage( 'DEVOPS:Page_with_invalid_title' );
		$this->assertTrue( $skip, 'Page should be skipped (invalid title)' );

		// Test a page that is set in the body_content_invalids database table
		$skip = $skipHelper->skipPage( 'DEVOPS:Page_with_invalid_content_length' );
		$this->assertTrue( $skip, 'Page should be skipped (invalid content length)' );

		// Test a page that is not set in the page_invalid_titles database table
		$skip = $skipHelper->skipPage( 'ABC:SomePage' );
		$this->assertFalse( $skip, 'Page should not be skipped' );

		$skipHelper = $this->getSkipHelper(
			$this->getMigrationConfig()
		);

		// Test a page that is not set in one of the "invaild" tables but skip by configuration
		$skip = $skipHelper->skipPage( 'ABC:Some_MediaWiki_page_name' );
		$this->assertTrue( $skip, 'Page should be skipped (by configuration)' );

		$skip = $skipHelper->skipPage( 'DEVOPS:Page_Title3' );
		$this->assertTrue( $skip, 'Page should be skipped (by configuration)' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipPage()
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
		$skip = $skipHelper->skipPage( $pageId );
		$this->assertTrue( $skip, 'BlogPost should be skipped (invalid title)' );

		// Test a page that is set in the body_content_invalids database table
		$pageId = null;
		if ( isset( $map['Blog:DEVOPS/BlogPost_with_invalid_content_length'] ) ) {
			$pageId = $map['Blog:DEVOPS/BlogPost_with_invalid_content_length'];
		}
		$this->assertNotNull( $pageId, 'BlogPost id should not be null' );
		$skip = $skipHelper->skipPage( $pageId );
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

		$skipHelper = $this->getSkipHelper(
			$this->getMigrationConfig()
		);

		// Test skip by configured namespace
		$skip = $skipHelper->skipWikiTitle( 'DEVOPS:Page_Title3' );
		$this->assertTrue( $skip, 'Page should be skipped (namespace in skip list)' );

		$skip = $skipHelper->skipWikiTitle( 'Blog:DEVOPS/Some_Blog_Post' );
		$this->assertTrue( $skip, 'BlogPost should be skipped (namespace in skip list)' );

		// Test skip by configured title
		$skip = $skipHelper->skipWikiTitle( 'ABC:Some_MediaWiki_page_name' );
		$this->assertTrue( $skip, 'Page should be skipped (title in skip list)' );

		// Test a page not matching any skip rule
		$skip = $skipHelper->skipWikiTitle( 'ABC:SomePage' );
		$this->assertFalse( $skip, 'Page should not be skipped' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipTemplate()
	 */
	public function testSkipTemplate() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);

		// Template exists in DB and is not invalid
		$skip = $skipHelper->skipTemplate( 'Template:ABC/SomePage' );
		$this->assertFalse( $skip, 'Template should not be skipped' );

		// Template does not exist in DB at all → treated as invalid
		$skip = $skipHelper->skipTemplate( 'Template:Nonexistent/Page' );
		$this->assertTrue( $skip, 'Template should be skipped (not found in DB)' );

		$skipHelper = $this->getSkipHelper(
			$this->getMigrationConfig()
		);

		// Template title matches configured skip titles
		$skip = $skipHelper->skipTemplate( 'Template:ABC/SomePage' );
		$this->assertTrue( $skip, 'Template should be skipped (title in skip list)' );

		// Template exists and matches no skip rule
		$skip = $skipHelper->skipTemplate( 'Template:DEVOPS/SomeOtherPage' );
		$this->assertFalse( $skip, 'Template should not be skipped' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipWikiTitle()
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipPage()
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipBlogPost()
	 * @covers \HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper::skipTemplate()
	 */
	public function testSkipNullAndEmpty() {
		$skipHelper = $this->getSkipHelper(
			$this->getEmptyMigrationConfig()
		);

		$this->assertTrue( $skipHelper->skipWikiTitle( null ), 'skipWikiTitle(null) should return true' );
		$this->assertTrue( $skipHelper->skipWikiTitle( '' ), 'skipWikiTitle("") should return true' );

		$this->assertTrue( $skipHelper->skipPage( null ), 'skipPage(null) should return true' );
		$this->assertTrue( $skipHelper->skipPage( '' ), 'skipPage("") should return true' );

		$this->assertTrue( $skipHelper->skipBlogPost( null ), 'skipBlogPost(null) should return true' );
		$this->assertTrue( $skipHelper->skipBlogPost( '' ), 'skipBlogPost("") should return true' );

		$this->assertTrue( $skipHelper->skipTemplate( null ), 'skipTemplate(null) should return true' );
		$this->assertTrue( $skipHelper->skipTemplate( '' ), 'skipTemplate("") should return true' );
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
				'ABC:Some_MediaWiki_page_name',
				'Template:ABC/SomePage'
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
