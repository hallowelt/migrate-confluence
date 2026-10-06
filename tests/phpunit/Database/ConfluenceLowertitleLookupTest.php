<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests making sure title-based lookups compare against the stored
 * confluence_lowertitle column (case-insensitive, and using whatever value
 * Confluence/the extractor actually put there) instead of recomputing the
 * lowercase form from confluence_title on the fly.
 */
class ConfluenceLowertitleLookupTest extends TestCase {

	private const SPACE_ID = 1000;

	private function createWorkspaceDB(): WorkspaceDB {
		return ( new WorkspaceDbMock() )->createEmpty();
	}

	private function addSpace( WorkspaceDB $db ): void {
		$db->addSpace( self::SPACE_ID, 'TEST', 'Test Space', 'TEST', '', '', -1, -1 );
	}

	private function addPage(
		WorkspaceDB $db, int $pageId, string $confluenceTitle, string $confluenceLowertitle, string $wikiTitle
	): void {
		$db->addPage(
			$pageId, self::SPACE_ID, $confluenceTitle, $confluenceLowertitle, $wikiTitle,
			'current', '20240101000000', '', '1', -1, -1, [], [], [], []
		);
	}

	/**
	 * Adds a page that was deleted/archived in Confluence: it has no current, live
	 * version anymore, but its (non-current) row with the matching title is still
	 * present in the export/DB.
	 */
	private function addNonCurrentPage(
		WorkspaceDB $db, int $pageId, string $confluenceTitle, string $confluenceLowertitle, string $contentStatus
	): void {
		$db->addPage(
			$pageId, self::SPACE_ID, $confluenceTitle, $confluenceLowertitle, '',
			$contentStatus, '20240101000000', '', '1', -1, -1, [], [], [], []
		);
	}

	private function addBlogPost(
		WorkspaceDB $db, int $pageId, string $confluenceTitle, string $confluenceLowertitle, string $wikiTitle
	): void {
		$db->addBlogPost(
			$pageId, self::SPACE_ID, $confluenceTitle, $confluenceLowertitle, $wikiTitle,
			'current', '20240101000000', '', '1', -1, [], [], [], []
		);
	}

	/**
	 * Adds a blog post that was deleted/archived in Confluence: it has no current,
	 * live version anymore, but its (non-current) row with the matching title is
	 * still present in the export/DB.
	 */
	private function addNonCurrentBlogPost(
		WorkspaceDB $db, int $pageId, string $confluenceTitle, string $confluenceLowertitle, string $contentStatus
	): void {
		$db->addBlogPost(
			$pageId, self::SPACE_ID, $confluenceTitle, $confluenceLowertitle, '',
			$contentStatus, '20240101000000', '', '1', -1, [], [], [], []
		);
	}

	private function addPageAttachment(
		WorkspaceDB $db, int $attachmentId, int $pageId, string $originalFilename, string $targetFilename
	): void {
		$db->addAttachment(
			$attachmentId, self::SPACE_ID, $originalFilename, 'txt', $pageId,
			'current', '1', '20240101000000', '', -1, '', [], [], []
		);
		$db->addPageAttachment( $attachmentId, $pageId, $originalFilename, $targetFilename );
	}

	private function addBlogPostAttachment(
		WorkspaceDB $db, int $attachmentId, int $blogPostId, string $originalFilename, string $targetFilename
	): void {
		$db->addAttachment(
			$attachmentId, self::SPACE_ID, $originalFilename, 'txt', $blogPostId,
			'current', '1', '20240101000000', '', -1, '', [], [], []
		);
		$db->addBlogPostAttachment( $attachmentId, $blogPostId, $originalFilename, $targetFilename );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiPageTitleFromSpaceId
	 */
	public function testGetWikiPageTitleFromSpaceIdIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addPage( $db, 600, 'Mixed Case Page', 'mixed case page', 'TEST:Mixed_Case_Page' );

		$this->assertSame(
			'TEST:Mixed_Case_Page',
			$db->getWikiPageTitleFromSpaceId( self::SPACE_ID, 'MIXED CASE PAGE' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiPageTitleFromSpaceId
	 */
	public function testGetWikiPageTitleFromSpaceIdComparesAgainstStoredLowertitle(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		// confluence_lowertitle deliberately does not equal strtolower(confluence_title),
		// as Confluence may supply its own lowerTitle value (see 47dc084).
		$this->addPage( $db, 601, 'Original Title', 'custom-lowertitle', 'TEST:Original_Title' );

		$this->assertSame(
			'TEST:Original_Title',
			$db->getWikiPageTitleFromSpaceId( self::SPACE_ID, 'custom-lowertitle' ),
			'Lookup must match the stored confluence_lowertitle value.'
		);
		$this->assertNull(
			$db->getWikiPageTitleFromSpaceId( self::SPACE_ID, 'Original Title' ),
			'Lookup must not fall back to a recomputed lower(confluence_title).'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getPageTitlesFromSpaceId
	 */
	public function testGetPageTitlesFromSpaceIdIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addPage( $db, 602, 'Another Page', 'another page', 'TEST:Another_Page' );

		$result = $db->getPageTitlesFromSpaceId( self::SPACE_ID, 'ANOTHER PAGE' );

		$this->assertSame( 'TEST:Another_Page', $result['wiki_title'] );
	}

	/**
	 * Regression test: if a page was deleted/archived in Confluence (no current, live
	 * version left with that title), a link to its title must not resolve, even though
	 * a non-current row with a matching confluence_lowertitle is still present in the DB.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiPageTitleFromSpaceId
	 */
	public function testGetWikiPageTitleFromSpaceIdDoesNotResolveDeletedPage(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addNonCurrentPage( $db, 606, 'Archived Page', 'archived page', 'archived' );

		$this->assertNull(
			$db->getWikiPageTitleFromSpaceId( self::SPACE_ID, 'archived page' ),
			'A page without a current version must not be resolved by title.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiPageTitleFromSpaceId
	 */
	public function testGetWikiPageTitleFromSpaceIdResolvesCurrentPageDespiteNonCurrentDuplicate(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		// A trashed/historical row sharing the same title as the live page.
		$this->addNonCurrentPage( $db, 607, 'Reused Title', 'reused title', 'trashed' );
		$this->addPage( $db, 608, 'Reused Title', 'reused title', 'TEST:Reused_Title' );

		$this->assertSame(
			'TEST:Reused_Title',
			$db->getWikiPageTitleFromSpaceId( self::SPACE_ID, 'reused title' ),
			'Lookup must resolve to the current page, not the non-current duplicate row.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiBlogPostTitleFromSpaceId
	 */
	public function testGetWikiBlogPostTitleFromSpaceIdIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addBlogPost( $db, 700, 'Mixed Case Post', 'mixed case post', 'Blog:TEST/Mixed_Case_Post' );

		$this->assertSame(
			'Blog:TEST/Mixed_Case_Post',
			$db->getWikiBlogPostTitleFromSpaceId( self::SPACE_ID, 'MIXED CASE POST' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiBlogPostTitleFromSpaceId
	 */
	public function testGetWikiBlogPostTitleFromSpaceIdComparesAgainstStoredLowertitle(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addBlogPost( $db, 701, 'Original Post', 'custom-lowertitle', 'Blog:TEST/Original_Post' );

		$this->assertSame(
			'Blog:TEST/Original_Post',
			$db->getWikiBlogPostTitleFromSpaceId( self::SPACE_ID, 'custom-lowertitle' )
		);
		$this->assertNull( $db->getWikiBlogPostTitleFromSpaceId( self::SPACE_ID, 'Original Post' ) );
	}

	/**
	 * Regression test: if a blog post was deleted/archived in Confluence (no current,
	 * live version left with that title), a link to its title must not resolve.
	 *
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiBlogPostTitleFromSpaceId
	 */
	public function testGetWikiBlogPostTitleFromSpaceIdDoesNotResolveDeletedBlogPost(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addNonCurrentBlogPost( $db, 705, 'Archived Post', 'archived post', 'archived' );

		$this->assertNull(
			$db->getWikiBlogPostTitleFromSpaceId( self::SPACE_ID, 'archived post' ),
			'A blog post without a current version must not be resolved by title.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiFileTitleFromSpaceId
	 */
	public function testGetWikiFileTitleFromSpaceIdIsCaseInsensitiveForPageAttachments(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addPage( $db, 603, 'Page With File', 'page with file', 'TEST:Page_With_File' );
		$this->addPageAttachment( $db, 20001, 603, 'file.txt', 'TEST_Page_With_File-file.txt' );

		$this->assertSame(
			'TEST_Page_With_File-file.txt',
			$db->getWikiFileTitleFromSpaceId( self::SPACE_ID, 'PAGE WITH FILE', 'file.txt' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiFileTitleFromSpaceId
	 */
	public function testGetWikiFileTitleFromSpaceIdIsCaseInsensitiveForBlogPostAttachments(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addBlogPost( $db, 702, 'Post With File', 'post with file', 'Blog:TEST/Post_With_File' );
		$this->addBlogPostAttachment( $db, 20002, 702, 'file.txt', 'Blog_TEST_Post_With_File-file.txt' );

		$this->assertSame(
			'Blog_TEST_Post_With_File-file.txt',
			$db->getWikiFileTitleFromSpaceId( self::SPACE_ID, 'POST WITH FILE', 'file.txt' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiFileTitlesForPage
	 */
	public function testGetWikiFileTitlesForPageIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addPage( $db, 604, 'Page With Files', 'page with files', 'TEST:Page_With_Files' );
		$this->addPageAttachment( $db, 20003, 604, 'a.txt', 'TEST_Page_With_Files-a.txt' );

		$this->assertSame(
			[ 'TEST_Page_With_Files-a.txt' ],
			$db->getWikiFileTitlesForPage( self::SPACE_ID, 'PAGE WITH FILES' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getWikiFileTitlesForBlogPost
	 */
	public function testGetWikiFileTitlesForBlogPostIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addBlogPost( $db, 703, 'Post With Files', 'post with files', 'Blog:TEST/Post_With_Files' );
		$this->addBlogPostAttachment( $db, 20004, 703, 'a.txt', 'Blog_TEST_Post_With_Files-a.txt' );

		$this->assertSame(
			[ 'Blog_TEST_Post_With_Files-a.txt' ],
			$db->getWikiFileTitlesForBlogPost( self::SPACE_ID, 'POST WITH FILES' )
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getAttachmentMetadataForPage
	 */
	public function testGetAttachmentMetadataForPageIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addPage( $db, 605, 'Page With Meta', 'page with meta', 'TEST:Page_With_Meta' );
		$this->addPageAttachment( $db, 20005, 605, 'a.txt', 'TEST_Page_With_Meta-a.txt' );

		$result = $db->getAttachmentMetadataForPage( self::SPACE_ID, 'PAGE WITH META' );

		$this->assertSame( 'TEST_Page_With_Meta-a.txt', $result['a.txt']['targetTitle'] );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Database\WorkspaceDB::getAttachmentMetadataForBlogPost
	 */
	public function testGetAttachmentMetadataForBlogPostIsCaseInsensitive(): void {
		$db = $this->createWorkspaceDB();
		$this->addSpace( $db );
		$this->addBlogPost( $db, 704, 'Post With Meta', 'post with meta', 'Blog:TEST/Post_With_Meta' );
		$this->addBlogPostAttachment( $db, 20006, 704, 'a.txt', 'Blog_TEST_Post_With_Meta-a.txt' );

		$result = $db->getAttachmentMetadataForBlogPost( self::SPACE_ID, 'POST WITH META' );

		$this->assertSame( 'Blog_TEST_Post_With_Meta-a.txt', $result['a.txt']['targetTitle'] );
	}
}
