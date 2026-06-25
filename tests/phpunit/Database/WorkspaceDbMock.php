<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class WorkspaceDbMock {
	private int $nextTestPageId = 10000;

	private int $nextTestBlogPostId = 3000;

	private int $nextTestBodyContentId = 80000;

	private int $nextTestAttachmentId = 20000;

	/** @var array<string,int> */
	private array $pageIds = [];

	/**
	 * @return WorkspaceDB
	 */
	public function createEmpty(): WorkspaceDB {
		$workspaceDB = $this->createWorkspaceDB(
			'confluence-migration-test-' . uniqid( '', true ) . '/workspace.sqlite'
		);
		return $workspaceDB;
	}

	/**
	 * Create a fixture database without ext-ns-file-repo-compat behavior.
	 * Attachment wiki titles replace namespace colons with underscores.
	 */
	public function createWithoutExtNsFileRepoCompat(): WorkspaceDB {
		return $this->createFixtureDatabase( false );
	}

	/**
	 * Create a fixture database with ext-ns-file-repo-compat behavior.
	 * Attachment wiki titles keep namespace colons.
	 */
	public function createWithExtNsFileRepoCompat(): WorkspaceDB {
		return $this->createFixtureDatabase( true );
	}

	private function createFixtureDatabase( bool $keepAttachmentNamespaceColon ): WorkspaceDB {
		$this->nextTestPageId = 10000;
		$this->nextTestAttachmentId = 20000;
		$this->pageIds = [];

		$workspaceDB = $this->createWorkspaceDB(
			'confluence-migration-test-' . uniqid( '', true ) . '/workspace.sqlite'
		);

		$this->seedDefaultSpaces( $workspaceDB );
		$this->seedUsers( $workspaceDB );
		$this->seedPageMappings( $workspaceDB );
		$this->seedBlogPostMappings( $workspaceDB );
		$this->seedSpaceHomepages( $workspaceDB );
		$this->seedTableComplexMappings( $workspaceDB );
		$this->seedAttachmentMappings( $workspaceDB, $keepAttachmentNamespaceColon );
		$this->seedPageTemplateMappings( $workspaceDB );
		$this->seedInvalidTitlesAndContents( $workspaceDB );

		return $workspaceDB;
	}

	private function createWorkspaceDB( string $pathSuffix ): WorkspaceDB {
		return new WorkspaceDB( ':memory:' );
	}

	private function seedDefaultSpaces( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addSpace( 0, '', 'General', 'GENERAL:', -1, -1 );
		$workspaceDB->addSpace( 1, 'MKT', 'Marketing', 'MKT:', -1, -1 );
		$workspaceDB->addSpace( 23, 'DEVOPS', 'DevOps', 'DEVOPS:', -1, -1 );
		$workspaceDB->addSpace( 42, 'ABC', 'Some space', 'ABC:', -1, -1 );
		$workspaceDB->addSpace( 52, 'INF', 'Some other space', 'INF:', -1, -1 );
	}

	private function seedUsers( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addUser( 'abc123', 'Alice', 'alice@example.org', [] );
		$workspaceDB->addUser( 'def456', 'Bob', 'bob@example.org', [] );
		$workspaceDB->addUser( '123456', 'TheFirstUser', '', [] );
		$workspaceDB->addUser( '789456', 'TheSecondUser', '', [] );
		$workspaceDB->addUser( '8a24c45f93bbe67901943c7033640000', 'UserA', '', [] );
		$workspaceDB->addUser( '000000005e7f616b01606dc4e2080003', 'UserB', '', [] );
	}

	private function seedPageMappings( WorkspaceDB $workspaceDB ): void {
		$this->addPageMapping( $workspaceDB, 42, 'SomePage', 'ABC:SomePage' );
		$this->addPageMapping( $workspaceDB, 42, 'SomeLinkedPage', 'ABC:SomeLinkedPage' );
		$this->addPageMapping( $workspaceDB, 23, 'SomePage', 'DEVOPS:SomePage' );
		$this->addPageMapping( $workspaceDB, 23, 'Some other page', 'DEVOPS:Some_other_page' );
		$this->addPageMapping( $workspaceDB, 1, 'Brand Assets', 'MKT:Brand_Assets' );
		$this->addPageMapping( $workspaceDB, 42, 'Some page', 'ABC:Some_page' );
		$this->addPageMapping( $workspaceDB, 23, 'Some other page', 'DEVOPS:Some_other_page' );
		$this->addPageMapping( $workspaceDB, 42, 'Some Confluence page name', 'ABC:Some_MediaWiki_page_name' );
		$this->addPageMapping( $workspaceDB, 42, 'Page Title', 'ABC:Page_Title' );
		$this->addPageMapping( $workspaceDB, 42, 'Page Title2', 'ABC:Page_Title2' );
		$this->addPageMapping( $workspaceDB, 42, 'Page Title3', 'ABC:Page_Title3' );
		$this->addPageMapping( $workspaceDB, 42, 'Page Title5', 'ABC:Test/Page_Title5' );
		$this->addPageMapping( $workspaceDB, 23, 'Page Title3', 'DEVOPS:Page_Title3' );
		$this->addPageMapping( $workspaceDB, 23, 'Page Title3, Test', 'DEVOPS:Page_Title3/Test' );
		$this->addPageMapping( $workspaceDB, 0, 'Page Title6', 'Page_Title6' );
		$this->addPageMapping( $workspaceDB, 0, 'Page Title7', 'Test/Page_Title7' );

		$this->addPageMapping( $workspaceDB, 23, 'Main Page', 'DEVOPS:Main Page' );
		$this->addPageMapping( $workspaceDB, 23, 'Testpage', 'DEVOPS:Testpage' );
		$this->addPageMapping( $workspaceDB, 42, 'Main Page', 'ABC:Main Page' );
		$this->addPageMapping( $workspaceDB, 42, 'Testpage', 'ABC:SomeLinkedPage/Testpage' );

		$this->addPageMapping( $workspaceDB, 1, 'MyPage', 'MKT:MyPage' );
		$this->addPageMapping( $workspaceDB, 1, 'MyPage without attachments', 'MKT:MyPage_without_attachments' );
		$this->addPageMapping( $workspaceDB, 1, 'OtherPage', 'MKT:OtherPage' );
		$this->addPageMapping( $workspaceDB, 1, 'Marketing Assets', 'MKT:Marketing_Assets' );
		$this->addPageMapping( $workspaceDB, 1, 'Team Photos', 'MKT:Team_Photos' );

		$this->addPageMapping(
			$workspaceDB,
			52,
			'Sed do eiusmod tempor incididunt',
			'INF:Sed_do_eiusmod_tempor_incididunt'
		);
	}

	private function seedBlogPostMappings( WorkspaceDB $workspaceDB ): void {
		$this->addBlogPostMapping( $workspaceDB, 42, 'Some Blog Post', 'Blog:ABC/Some_Blog_Post' );
	}

	private function seedInvalidTitlesAndContents( WorkspaceDB $workspaceDB ): void {
		// Add pages to page_invalid_titles table
		$pageId = $this->addPageMapping(
			$workspaceDB, 23, 'Page with invalid title', 'DEVOPS:Page_with_invalid_title'
		);
		$workspaceDB->addInvalidPageWikiTitle(
			$pageId, 'DEVOPS:Page_with_invalid_title', 'Page has invalid title length'
		);
		$pageId = $this->addBlogPostMapping(
			$workspaceDB, 42, 'Some Blog Post with invalid title', 'Blog:ABC/Some_Blog_Post_with_invalid_title'
		);
		$workspaceDB->addInvalidBlogPostWikiTitle(
			$pageId, 'Blog:ABC/Some_Blog_Post_with_invalid_title', 'BlogPost has invalid title length'
		);

		// Add pages to body_content_invalids table
		$pageId = $this->addPageMapping(
			$workspaceDB, 23, 'Page with invalid content length', 'DEVOPS:Page_with_invalid_content_length'
		);
		$bodyContentIds = $workspaceDB->getBodyContentIdsForContentId( $pageId );
		$workspaceDB->addInvalidBodyContent( $bodyContentIds[0], 'Content length exeeded 512 ...' );

		$pageId = $this->addBlogPostMapping(
			$workspaceDB, 23, 'BlogPost with invalid content length', 'Blog:DEVOPS/BlogPost_with_invalid_content_length'
		);
		$bodyContentIds = $workspaceDB->getBodyContentIdsForContentId( $pageId );
		$workspaceDB->addInvalidBodyContent( $bodyContentIds[0], 'Content length exeeded 512 ...' );
	}

	private function seedTableComplexMappings( WorkspaceDB $workspaceDB ): void {
		$this->addPageMapping(
			$workspaceDB,
			23,
			'Sed_do_eiusmod_tempor_incididunt',
			'INF:Sed_do_eiusmod_tempor_incididunt'
		);
	}

	private function seedSpaceHomepages( WorkspaceDB $workspaceDB ): void {
		$mainPages = [
			0 => [ 'Page Title6', 'Page_Title6' ],
			1 => [ 'MyPage', 'MKT:MyPage' ],
			23 => [ 'Main Page', 'DEVOPS:Main Page' ],
			42 => [ 'Main Page', 'ABC:Main Page' ],
			52 => [ 'Sed do eiusmod tempor incididunt', 'INF:Sed_do_eiusmod_tempor_incididunt' ],
		];

		foreach ( $mainPages as $spaceId => $titles ) {
			$pageId = $this->findPageId( $workspaceDB, $spaceId, $titles[0], $titles[1] );
			$workspaceDB->updateSpaceHomepageId( $spaceId, $pageId );
		}
	}

	private function seedPageTemplateMappings( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addPageTemplate( 123456, 'SomePage', 42, 'Template:ABC/SomePage' );
		$workspaceDB->addPageTemplate( 7890, 'SomeOtherPage', 23, 'Template:DEVOPS/SomeOtherPage' );
	}

	private function seedAttachmentMappings( WorkspaceDB $workspaceDB, bool $keepAttachmentNamespaceColon ): void {
		$this->addPageWithGeneratedAttachments(
			$workspaceDB,
			0,
			'SomePage',
			'SomePage',
			[
				'Dummy_1.pdf',
				'Dummy_2.docx',
				'Dummy_2.doc',
				'Dummy_3.png',
				'Dummy_3.xls',
				'Dummy_1.ppt',
				'SomeImage2.png',
				'drawio.png',
				'gliffy-file-1.png',
				'gliffy-file-2.png',
				'gliffy-file-2.svg'
			],
			$keepAttachmentNamespaceColon
		);
		$this->addPageWithGeneratedAttachments(
			$workspaceDB,
			23,
			'SomePage',
			'DEVOPS:SomePage',
			[
				'Dummy_1.pdf',
				'Dummy_2.docx',
				'Dummy_2.doc',
				'Dummy_3.png',
				'Dummy_3.xls',
				'Dummy_1.ppt',
				'SomeImage2.png',
				'drawio.png',
				'gliffy-file-1.png',
				'gliffy-file-2.png',
				'gliffy-file-2.svg'
			],
			$keepAttachmentNamespaceColon
		);
		$this->addPageWithGeneratedAttachments(
			$workspaceDB,
			42,
			'SomePage',
			'ABC:SomePage',
			[
				'SomeImage.png',
				'SomeImage1.png',
			],
			$keepAttachmentNamespaceColon
		);

		$myPageId = $this->findPageId( $workspaceDB, 1, 'MyPage', 'MKT:MyPage' );
		$otherPageId = $this->findPageId( $workspaceDB, 1, 'OtherPage', 'MKT:OtherPage' );
		$brandAssetsPageId = $this->findPageId( $workspaceDB, 1, 'Brand Assets', 'MKT:Brand_Assets' );
		$teamPhotosPageId = $this->findPageId( $workspaceDB, 1, 'Team Photos', 'MKT:Team_Photos' );
		$marketingAssetsPageId = $this->findPageId( $workspaceDB, 1, 'Marketing Assets', 'MKT:Marketing_Assets' );

		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'dashboard.png', 'dashboard.png' );
		$this->addAttachmentMapping(
			$workspaceDB,
			1,
			$myPageId,
			'photo.jpg',
			'photo.jpg',
			[ 'labels' => [ 'featured' ] ]
		);
		$this->addAttachmentMapping(
			$workspaceDB,
			1,
			$myPageId,
			'loading.gif',
			'loading.gif',
			[ 'labels' => [ 'featured', 'draft' ] ]
		);
		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'illustration.webp', 'illustration.webp' );
		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'raw-scan.bmp', 'raw-scan.bmp' );
		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'blueprint.tiff', 'blueprint.tiff' );
		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'icon.svg', 'icon.svg' );
		$this->addAttachmentMapping( $workspaceDB, 1, $myPageId, 'document.pdf', 'document.pdf' );
		$this->addAttachmentMapping(
			$workspaceDB,
			1,
			$myPageId,
			'approved.png',
			'approved.png',
			[ 'labels' => [ 'approved' ] ]
		);
		$this->addAttachmentMapping(
			$workspaceDB,
			1,
			$myPageId,
			'hero.jpg',
			'hero.jpg',
			[ 'labels' => [ 'featured', 'approved' ] ]
		);
		$this->addAttachmentMapping(
			$workspaceDB,
			1,
			$myPageId,
			'rejected.png',
			'rejected.png',
			[ 'labels' => [ 'featured', 'approved', 'draft' ] ]
		);

		$this->addAttachmentMapping( $workspaceDB, 1, $otherPageId, 'report.pdf', 'report.pdf' );
		$this->addAttachmentMapping( $workspaceDB, 1, $otherPageId, 'chart.png', 'chart.png' );
		$this->addAttachmentMapping( $workspaceDB, 1, $brandAssetsPageId, 'logo.png', 'logo.png' );
		$this->addAttachmentMapping( $workspaceDB, 1, $teamPhotosPageId, 'team.jpg', 'team.jpg' );
		$this->addAttachmentMapping( $workspaceDB, 1, $marketingAssetsPageId, 'logo.png', 'logo.png' );
	}

	private function addPageWithGeneratedAttachments(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		array $attachments,
		bool $keepAttachmentNamespaceColon
	): int {
		$pageId = $this->findPageId( $workspaceDB, $spaceId, $confluenceTitle, $wikiTitle );

		foreach ( $attachments as $attachment ) {
			$this->addAttachmentMapping(
				$workspaceDB,
				$spaceId,
				$pageId,
				$attachment,
				$this->buildAttachmentWikiTitle( $wikiTitle, $attachment, $keepAttachmentNamespaceColon )
			);
		}

		return $pageId;
	}

	private function buildAttachmentWikiTitle(
		string $pageWikiTitle,
		string $attachmentTitle,
		bool $keepAttachmentNamespaceColon
	): string {
		if ( !$keepAttachmentNamespaceColon ) {
			$pageWikiTitle = str_replace( ':', '_', $pageWikiTitle );
		}

		return $pageWikiTitle . '-' . $attachmentTitle;
	}

	private function findPageId(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle
	): int {
		$pageKey = $spaceId . '|' . $confluenceTitle . '|' . $wikiTitle;

		if ( isset( $this->pageIds[$pageKey] ) ) {
			return $this->pageIds[$pageKey];
		}

		return $this->addPageMapping( $workspaceDB, $spaceId, $confluenceTitle, $wikiTitle );
	}

	private function addPageMapping(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle
	): int {
		$pageId = $this->nextTestPageId++;
		$pageKey = $spaceId . '|' . $confluenceTitle . '|' . $wikiTitle;
		$this->pageIds[$pageKey] = $pageId;

		$bodyContentId = $this->addBodyContent( $workspaceDB, $pageId, 'Page' );

		$workspaceDB->addPage(
			$pageId,
			$spaceId,
			$confluenceTitle,
			$wikiTitle,
			'20240101000000',
			'',
			'1',
			-1,
			-1,
			[ $bodyContentId ],
			[],
			[],
			[]
		);

		return $pageId;
	}

	private function addBlogPostMapping(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle
	): int {
		$pageId = $this->nextTestBlogPostId++;
		$pageKey = $spaceId . '|' . $confluenceTitle . '|' . $wikiTitle;
		$this->pageIds[$pageKey] = $pageId;

		$bodyContentId = $this->addBodyContent( $workspaceDB, $pageId, 'BlogPost' );

		$workspaceDB->addBlogPost(
			$pageId,
			$spaceId,
			$confluenceTitle,
			$wikiTitle,
			'20240101000000',
			'',
			'1',
			-1,
			[ $bodyContentId ],
			[],
			[],
			[]
		);

		return $pageId;
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param int $contentId
	 * @param string $class
	 * @return int
	 */
	private function addBodyContent(
		WorkspaceDB $workspaceDB, int $contentId, string $class
	): int {
		$this->nextTestBodyContentId++;
		$status = $workspaceDB->addBodyContent(
			$this->nextTestBodyContentId,
			$contentId,
			$class,
			[]
		);

		return $this->nextTestBodyContentId;
	}

	private function addAttachmentMapping(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		int $pageId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename,
		array $meta = [],
		string $attachmentReference = ''
	): int {
		$attachmentId = $this->nextTestAttachmentId++;
		$fileExtension = pathinfo( $originalAttachmentFilename, PATHINFO_EXTENSION );

		$workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$originalAttachmentFilename,
			$fileExtension,
			$pageId,
			'1',
			'20240101000000',
			'',
			-1,
			$attachmentReference,
			[],
			[],
			[]
		);
		$workspaceDB->addPageAttachment(
			$attachmentId,
			$pageId,
			$originalAttachmentFilename,
			$targetAttachmentFilename
		);

		if ( $meta !== [] ) {
			$workspaceDB->addAttachmentMeta( $attachmentId, $meta );
		}

		return $attachmentId;
	}
}
