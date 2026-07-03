<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class ComprehensiveMockDatabase {
	private int $nextPageId = 10000;
	private int $nextBlogPostId = 5000;
	private int $nextBodyContentId = 80000;
	private int $nextSpaceDescriptionId = 1000;
	private int $nextAttachmentId = 20000;
	/** @var array<int, int> Map of spaceId to homepage pageId */
	private array $spaceHomepages = [];
	/** @var array<int, int> Map of spaceId to description pageId */
	private array $spaceDescriptionIds = [];
	/** @var array<int, array<int>> Map of spaceId to list of page IDs */
	private array $spacePages = [];
	/** @var array<int, array<int>> Map of spaceId to list of blog post IDs */
	private array $spaceBlogs = [];

	/**
	 * Create a comprehensive test database with 4 spaces.
	 * Each space contains 10 pages and 5 blog posts.
	 * Each page/blog post has 1-3 revisions and 1-10 attachments.
	 * Each space has 1-3 additional attachments.
	 * Each space has a homepage and description.
	 *
	 * @return WorkspaceDB
	 */
	public function create(): WorkspaceDB {
		$workspaceDB = $this->createWorkspaceDB(
			'comprehensive-test-' . uniqid( '', true ) . '/workspace.sqlite'
		);

		$this->seedSpaceDescriptions( $workspaceDB );
		$this->seedSpaces( $workspaceDB );
		$this->seedUsers( $workspaceDB );
		$this->seedPagesAndBlogs( $workspaceDB );
		$this->seedSpaceHomepages( $workspaceDB );
		$this->seedAttachments( $workspaceDB );

		return $workspaceDB;
	}

	private function createWorkspaceDB( string $pathSuffix ): WorkspaceDB {
		$databasePath = sys_get_temp_dir() . '/' . $pathSuffix;
		$databaseDir = dirname( $databasePath );

		if ( !is_dir( $databaseDir ) && !mkdir( $databaseDir, 0777, true ) && !is_dir( $databaseDir ) ) {
			throw new \RuntimeException( 'Could not create temporary database directory: ' . $databaseDir );
		}

		if ( file_exists( $databasePath ) ) {
			unlink( $databasePath );
		}

		return new WorkspaceDB( $databasePath );
	}

	private function seedSpaceDescriptions( WorkspaceDB $workspaceDB ): void {
		for ( $spaceId = 1; $spaceId <= 4; $spaceId++ ) {
			$spaceDescriptionId = $this->nextSpaceDescriptionId++;
			$this->spaceDescriptionIds[$spaceId] = $spaceDescriptionId;

			$bodyContentId = $this->addBodyContent( $workspaceDB, $spaceDescriptionId );

			$workspaceDB->addSpaceDescription(
				$spaceDescriptionId,
				'current',
				'1',
				-1,
				'20240101000000',
				[ $bodyContentId ],
				[],
				[],
				[]
			);
		}
	}

	private function seedSpaces( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addSpace( 1, 'SPACE1', 'Space One', 'SPACE1', 'wiki-space1', 'One', -1, $this->spaceDescriptionIds[1] );
		$workspaceDB->addSpace( 2, 'SPACE2', 'Space Two', 'SPACE2', 'wiki-space2', '', -1, $this->spaceDescriptionIds[2] );
		$workspaceDB->addSpace( 3, 'SPACE3', 'Space Three', 'SPACE3', 'wiki-space3', '', -1, $this->spaceDescriptionIds[3] );
		$workspaceDB->addSpace( 4, 'SPACE4', 'Space One', 'SPACE4', 'wiki-space4', 'Four', -1, $this->spaceDescriptionIds[4] );
	}

	private function seedUsers( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addUser( 'user1', 'User One', 'user1@example.org', [] );
		$workspaceDB->addUser( 'user2', 'User Two', 'user2@example.org', [] );
		$workspaceDB->addUser( 'user3', 'User Three', 'user3@example.org', [] );
	}

	private function seedPagesAndBlogs( WorkspaceDB $workspaceDB ): void {
		// Create pages and blogs for each space
		for ( $spaceId = 1; $spaceId <= 4; $spaceId++ ) {
			$this->spacePages[$spaceId] = [];
			$this->spaceBlogs[$spaceId] = [];

			// Add 10 pages to each space
			for ( $pageNum = 1; $pageNum <= 10; $pageNum++ ) {
				$confluenceTitle = "Page {$pageNum}";
				$wikiTitle = "SPACE{$spaceId}:Page_{$pageNum}";
				$pageId = $this->nextPageId++;

				$this->spacePages[$spaceId][] = $pageId;

				// Track first page as homepage
				if ( $pageNum === 1 ) {
					$this->spaceHomepages[$spaceId] = $pageId;
				}

			// Add 2 revisions for this page
			$revisionCount = 2;
				$bodyContentIds = [];
				for ( $rev = 1; $rev <= $revisionCount; $rev++ ) {
					$bodyContentIds[] = $this->addBodyContent( $workspaceDB, $pageId );
				}

				$workspaceDB->addPage(
					$pageId,
					$spaceId,
					$confluenceTitle,
					$wikiTitle,
					'Page content',
					'current',
					'20240101000000',
					'user1',
					'1',
					-1,
					-1,
					$bodyContentIds,
					[],
					[],
					[]
				);
			}

			// Add 5 blog posts to each space
			for ( $blogNum = 1; $blogNum <= 5; $blogNum++ ) {
				$confluenceTitle = "Blog Post {$blogNum}";
				$wikiTitle = "Blog:SPACE{$spaceId}/Blog_Post_{$blogNum}";
				$blogPostId = $this->nextBlogPostId++;

				$this->spaceBlogs[$spaceId][] = $blogPostId;

			// Add 2 revisions for this blog post
			$revisionCount = 2;
				$bodyContentIds = [];
				for ( $rev = 1; $rev <= $revisionCount; $rev++ ) {
					$bodyContentIds[] = $this->addBodyContent( $workspaceDB, $blogPostId );
				}

				$workspaceDB->addBlogPost(
					$blogPostId,
					$spaceId,
					$confluenceTitle,
					$wikiTitle,
					'Blog content',
					'current',
					'20240101000000',
					'user1',
					'1',
					-1,
					$bodyContentIds,
					[],
					[],
					[]
				);
			}
		}
	}

	private function addBodyContent( WorkspaceDB $workspaceDB, int $contentId ): int {
		$bodyContentId = $this->nextBodyContentId++;
		$workspaceDB->addBodyContent(
			$bodyContentId,
			$contentId,
			'Page',
			[]
		);
		return $bodyContentId;
	}

	private function seedSpaceHomepages( WorkspaceDB $workspaceDB ): void {
		// Set space homepages (Page 1 of each space)
		$workspaceDB->updateSpaceHomepageId( 1, $this->spaceHomepages[1] );
		$workspaceDB->updateSpaceHomepageId( 2, $this->spaceHomepages[2] );
		$workspaceDB->updateSpaceHomepageId( 3, $this->spaceHomepages[3] );
		$workspaceDB->updateSpaceHomepageId( 4, $this->spaceHomepages[4] );
	}

	private function seedAttachments( WorkspaceDB $workspaceDB ): void {
		$attachmentExtensions = [ 'pdf', 'docx', 'xlsx', 'pptx', 'png', 'jpg', 'gif', 'zip', 'txt', 'mp4' ];

		// Add attachments to pages and blogs
		for ( $spaceId = 1; $spaceId <= 4; $spaceId++ ) {
			// Add attachments to pages (5 per page)
			foreach ( $this->spacePages[$spaceId] as $pageId ) {
				$attachmentCount = 5;
				for ( $i = 1; $i <= $attachmentCount; $i++ ) {
					$extension = $attachmentExtensions[($pageId + $i) % count( $attachmentExtensions )];
					$filename = "attachment_{$pageId}_{$i}.{$extension}";
					$this->addAttachmentToContent( $workspaceDB, $spaceId, $pageId, $filename );
				}
			}

			// Add attachments to blog posts (5 per blog)
			foreach ( $this->spaceBlogs[$spaceId] as $blogPostId ) {
				$attachmentCount = 5;
				for ( $i = 1; $i <= $attachmentCount; $i++ ) {
					$extension = $attachmentExtensions[($blogPostId + $i) % count( $attachmentExtensions )];
					$filename = "blog_attachment_{$blogPostId}_{$i}.{$extension}";
					$this->addAttachmentToContent( $workspaceDB, $spaceId, $blogPostId, $filename );
				}
			}

			// Add 2 space-level attachments (not tied to any page/blog)
			$spaceAttachmentCount = 2;
			for ( $i = 1; $i <= $spaceAttachmentCount; $i++ ) {
				$extension = $attachmentExtensions[($spaceId + $i) % count( $attachmentExtensions )];
				$filename = "space_{$spaceId}_attachment_{$i}.{$extension}";
				$this->addSpaceAttachment( $workspaceDB, $spaceId, $filename );
			}
		}
	}

	private function addAttachmentToContent(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		int $contentId,
		string $filename
	): void {
		$attachmentId = $this->nextAttachmentId++;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		$workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$filename,
			$extension,
			$contentId,
			'current',
			'1',
			'20240101000000',
			'',
			-1,
			'',
			[],
			[],
			[]
		);

		$workspaceDB->addPageAttachment(
			$attachmentId,
			$contentId,
			$filename,
			$filename
		);
	}

	private function addSpaceAttachment(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		string $filename
	): void {
		$attachmentId = $this->nextAttachmentId++;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		// Space attachments use spaceId as contentId with -1 as the actual content ID
		$workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$filename,
			$extension,
			-1,
			'current',
			'1',
			'20240101000000',
			'',
			-1,
			'',
			[],
			[],
			[]
		);
	}
}
