<?php

namespace HalloWelt\MigrateConfluence\Tests\Composer\Processor;

use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HalloWelt\MigrateConfluence\Composer\Processor\Sidebar
 */
class SidebarTest extends TestCase {

	private string $tmpDir = '';

	private string $testNamespace = 'NS_MAIN';

	protected function setUp(): void {
		parent::setUp();
		$this->tmpDir = sys_get_temp_dir() . '/sidebar-test-' . uniqid( '', true );
		mkdir( $this->tmpDir . '/result', 0755, true );
	}

	protected function tearDown(): void {
		$this->deleteDir( $this->tmpDir );
		parent::tearDown();
	}

	private function makeSidebar(
		DBComposerDataLookup $dataLookup,
		bool $createSidebar = true
	): Sidebar {
		$config = $this->createMock( MigrationConfig::class );
		$config->method( 'getCreateSidebar' )->willReturn( $createSidebar );
		return new Sidebar( $dataLookup, $config, $this->tmpDir );
	}

	/**
	 * Parse enhanced-sidebar.xml and return the decoded JSON structure
	 */
	private function readSidebarJson(): array {
		$path = $this->tmpDir . '/result/' . $this->testNamespace . '/enhanced-sidebar.xml';
		$this->assertFileExists( $path );
		$xml = simplexml_load_file( $path );
		$this->assertNotFalse( $xml );
		$json = (string)$xml->page->revision->text;
		$data = json_decode( $json, true );
		$this->assertIsArray( $data );
		return $data;
	}

	private function executeSidebar( Sidebar $sidebar, array $spaces ): void {
		$sidebar->execute( $this->testNamespace, $spaces );
	}

	private function makeSpace( int $id, string $name ): array {
		return [ 'space_id' => (string)$id, 'space_name' => $name ];
	}

	private function makePage(
		int $id, string $wikiTitle, int $parentId = -1, int $position = 0,
		string $confluenceTitle = ''
	): array {
		return [
			'page_id'          => $id,
			'wiki_title'       => $wikiTitle,
			'confluence_title' => $confluenceTitle !== '' ? $confluenceTitle : $wikiTitle,
			'parent_page_id'   => $parentId,
			'position'         => $position,
		];
	}

	private function makeBlog( int $id, string $wikiTitle, string $confluenceTitle = '' ): array {
		return [
			'page_id'          => $id,
			'wiki_title'       => $wikiTitle,
			'confluence_title' => $confluenceTitle !== '' ? $confluenceTitle : $wikiTitle,
		];
	}

	/**
	 * create-sidebar: false
	 */
	public function testCreateSidebarFalseWritesNoFile(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->expects( $this->never() )->method( 'getPagesForSidebar' );

		$this->makeSidebar( $dataLookup, false )->execute( $this->testNamespace, [] );

		$this->assertFileDoesNotExist(
			$this->tmpDir . '/result/' . $this->testNamespace . '/enhanced-sidebar.xml'
		);
	}

	/**
	 * Single space
	 */
	public function testSingleSpacePagesAndBlogs(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->with( 1 )->willReturn( [
			$this->makePage( 10, 'PageA', -1, 100 ),
			$this->makePage( 20, 'PageB', -1, 200 ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->with( 1 )->willReturn( [
			$this->makeBlog( 30, 'Blog:Post1' ),
		] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		// Top level: space name heading
		$this->assertCount( 1, $sidebar );
		$spaceSection = $sidebar[0];
		$this->assertSame( 'enhanced-sidebar-panel-heading', $spaceSection['type'] );
		$this->assertSame( 'Space A', $spaceSection['text'] );

		// 2nd level: Pages and Blogs (both present)
		$this->assertCount( 2, $spaceSection['children'] );
		$this->assertSame( 'Pages', $spaceSection['children'][0]['text'] );
		$this->assertCount( 2, $spaceSection['children'][0]['children'] );
		$this->assertSame( 'Blogs', $spaceSection['children'][1]['text'] );
		$this->assertCount( 1, $spaceSection['children'][1]['children'] );
	}

	public function testSingleSpaceNoBlogPosts(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'PageA' ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		// Space heading, no Pages/Blogs sub-heading — entries directly as children
		$this->assertCount( 1, $sidebar );
		$this->assertSame( 'Space A', $sidebar[0]['text'] );
		$this->assertCount( 1, $sidebar[0]['children'] );
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[0]['children'][0]['type'] );
	}

	public function testSingleSpaceNoPagesOnlyBlogs(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [
			$this->makeBlog( 30, 'Blog:Post1' ),
		] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		// Space heading, blog entry directly as child (no Blogs sub-heading)
		$this->assertCount( 1, $sidebar );
		$this->assertSame( 'Space A', $sidebar[0]['text'] );
		$this->assertCount( 1, $sidebar[0]['children'] );
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[0]['children'][0]['type'] );
	}

	public function testSingleSpacePagesSortedByPosition(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'Second', -1, 200 ),
			$this->makePage( 20, 'First', -1, 100 ),
			$this->makePage( 30, 'Third', -1, 300 ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		// Only pages → direct children of space section
		$children = $sidebar[0]['children'];
		$this->assertSame( 'First', $children[0]['page'] );
		$this->assertSame( 'Second', $children[1]['page'] );
		$this->assertSame( 'Third', $children[2]['page'] );
	}

	public function testSingleSpaceNestedPages(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'Parent', -1, 100 ),
			$this->makePage( 20, 'Child', 10, 100 ),
			$this->makePage( 30, 'Grandchild', 20, 100 ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		// Only pages → direct children of space section
		$root = $sidebar[0]['children'];
		$this->assertCount( 1, $root );
		$this->assertSame( 'Parent', $root[0]['page'] );
		$this->assertArrayHasKey( 'children', $root[0] );

		$child = $root[0]['children'];
		$this->assertCount( 1, $child );
		$this->assertSame( 'Child', $child[0]['page'] );
		$this->assertArrayHasKey( 'children', $child[0] );

		$grandchild = $child[0]['children'];
		$this->assertCount( 1, $grandchild );
		$this->assertSame( 'Grandchild', $grandchild[0]['page'] );
		$this->assertArrayNotHasKey( 'children', $grandchild[0] );
	}

	public function testSingleSpaceLeafPageHasNoChildrenKey(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'Leaf', -1, 0 ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		$this->assertArrayNotHasKey( 'children', $sidebar[0]['children'][0] );
	}

	public function testDisplayTextUsesConfluenceTitle(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'MyNamespace:My_Page~1', -1, 0, 'My Page with a Very Long Original Title' ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		$link = $sidebar[0]['children'][0];
		$this->assertSame( 'My Page with a Very Long Original Title', $link['text'] );
		$this->assertSame( 'MyNamespace:My_Page~1', $link['page'] );
	}

	public function testDisplayTextUsesConfluenceTitleForBlogs(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [
			$this->makeBlog( 30, 'NS:Blog_post~1', 'My Blog Post Title' ),
		] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		$link = $sidebar[0]['children'][0];
		$this->assertSame( 'My Blog Post Title', $link['text'] );
		$this->assertSame( 'NS:Blog_post~1', $link['page'] );
	}

	public function testDisplayTextMatchesWikiTitleWhenNotMangled(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )->willReturn( [
			$this->makePage( 10, 'Spalten', -1, 0, 'Spalten' ),
		] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [ $this->makeSpace( 1, 'Space A' ) ] );
		$sidebar = $this->readSidebarJson();

		$link = $sidebar[0]['children'][0];
		$this->assertSame( 'Spalten', $link['text'] );
		$this->assertSame( 'Spalten', $link['page'] );
	}

	/**
	 * Multi-space (within same namespace)
	 */
	public function testMultiSpaceCreatesSpaceHeadings(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )
			->willReturnMap( [
				[ 1, [ $this->makePage( 10, 'NS_A:PageA' ) ] ],
				[ 2, [ $this->makePage( 20, 'NS_B:PageB' ) ] ],
			] );
		$dataLookup->method( 'getBlogPostsForSidebar' )
			->willReturnMap( [
				[ 1, [] ],
				[ 2, [] ],
			] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [
			$this->makeSpace( 1, 'Space A' ),
			$this->makeSpace( 2, 'Space B' ),
		] );
		$sidebar = $this->readSidebarJson();

		$this->assertCount( 2, $sidebar );
		$this->assertSame( 'Space A', $sidebar[0]['text'] );
		$this->assertSame( 'Space B', $sidebar[1]['text'] );

		// Only pages → direct children (no Pages sub-heading)
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[0]['children'][0]['type'] );
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[1]['children'][0]['type'] );
	}

	public function testMultiSpaceWithBlogsInOneSpace(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )
			->willReturnMap( [
				[ 1, [ $this->makePage( 10, 'PageA' ) ] ],
				[ 2, [] ],
			] );
		$dataLookup->method( 'getBlogPostsForSidebar' )
			->willReturnMap( [
				[ 1, [] ],
				[ 2, [ $this->makeBlog( 30, 'Blog:Post1' ) ] ],
			] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [
			$this->makeSpace( 1, 'Space A' ),
			$this->makeSpace( 2, 'Space B' ),
		] );
		$sidebar = $this->readSidebarJson();

		// Space A: only pages → direct children; Space B: only blogs → direct children
		$this->assertCount( 2, $sidebar );
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[0]['children'][0]['type'] );
		$this->assertSame( 'enhanced-sidebar-internal-link', $sidebar[1]['children'][0]['type'] );
	}

	public function testMultiSpaceBothPagesAndBlogsGetSubHeadings(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )
			->willReturnMap( [
				[ 1, [ $this->makePage( 10, 'PageA' ) ] ],
			] );
		$dataLookup->method( 'getBlogPostsForSidebar' )
			->willReturnMap( [
				[ 1, [ $this->makeBlog( 30, 'Blog:Post1' ) ] ],
			] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [
			$this->makeSpace( 1, 'Space A' ),
		] );
		$sidebar = $this->readSidebarJson();

		// Both → Pages and Blogs sub-headings
		$this->assertSame( 'Space A', $sidebar[0]['text'] );
		$this->assertSame( 'Pages', $sidebar[0]['children'][0]['text'] );
		$this->assertSame( 'Blogs', $sidebar[0]['children'][1]['text'] );
	}

	public function testMultiSpaceEmptySpaceIsSkipped(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )
			->willReturnMap( [
				[ 1, [ $this->makePage( 10, 'PageA' ) ] ],
				[ 2, [] ],
			] );
		$dataLookup->method( 'getBlogPostsForSidebar' )
			->willReturnMap( [
				[ 1, [] ],
				[ 2, [] ],
			] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [
			$this->makeSpace( 1, 'Space A' ),
			$this->makeSpace( 2, 'Empty Space' ),
		] );
		$sidebar = $this->readSidebarJson();

		$this->assertCount( 1, $sidebar );
		$this->assertSame( 'Space A', $sidebar[0]['text'] );
	}

	public function testMultiSpacePagesSortedByPosition(): void {
		$dataLookup = $this->createMock( DBComposerDataLookup::class );
		$dataLookup->method( 'getPagesForSidebar' )
			->willReturnMap( [
				[ 1, [
					$this->makePage( 10, 'Second', -1, 200 ),
					$this->makePage( 20, 'First', -1, 100 ),
				] ],
				[ 2, [] ],
			] );
		$dataLookup->method( 'getBlogPostsForSidebar' )->willReturn( [] );

		$this->executeSidebar( $this->makeSidebar( $dataLookup ), [
			$this->makeSpace( 1, 'Space A' ),
			$this->makeSpace( 2, 'Space B' ),
		] );
		$sidebar = $this->readSidebarJson();

		// Only pages → direct children of space section
		$pages = $sidebar[0]['children'];
		$this->assertSame( 'First', $pages[0]['page'] );
		$this->assertSame( 'Second', $pages[1]['page'] );
	}

	private function deleteDir( string $dir ): void {
		if ( $dir === '' || !is_dir( $dir ) ) {
			return;
		}
		foreach ( scandir( $dir ) ?: [] as $item ) {
			if ( $item === '.' || $item === '..' ) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->deleteDir( $path ) : unlink( $path );
		}
		rmdir( $dir );
	}
}
