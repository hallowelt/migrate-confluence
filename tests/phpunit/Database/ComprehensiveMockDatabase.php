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
	/** @var array<int, array<string, int>> Map of bodyContentId to content metadata */
	private array $bodyContentMetadata = [];
	/** @var array<int, array<int>> Map of pageId to list of body content IDs */
	private array $pageBodyContentIds = [];
	/** @var array<int, array<int>> Map of blogPostId to list of body content IDs */
	private array $blogBodyContentIds = [];

	/**
	 * Create a comprehensive test database with 4 spaces.
	 * Each space contains 10 pages and 5 blog posts.
	 * Each page/blog post has 2 revisions.
	 * Body content includes cross-space links to other pages and blogs.
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
		$this->seedBodyContentBodies( $workspaceDB );

		return $workspaceDB;
	}

	/**
	 * Create a comprehensive test database in a specific directory.
	 * Useful for integration tests that need the database in a specific location.
	 *
	 * @param string $baseDir The base directory where workspace.sqlite will be created
	 * @return WorkspaceDB
	 */
	public function createInDirectory( string $baseDir ): WorkspaceDB {
		$workspaceDB = $this->createWorkspaceDB( $baseDir . '/workspace.sqlite', false );

		$this->seedSpaceDescriptions( $workspaceDB );
		$this->seedSpaces( $workspaceDB );
		$this->seedUsers( $workspaceDB );
		$this->seedPagesAndBlogs( $workspaceDB );
		$this->seedSpaceHomepages( $workspaceDB );
		$this->seedAttachments( $workspaceDB );
		$this->seedBodyContentBodies( $workspaceDB );

		return $workspaceDB;
	}

	private function createWorkspaceDB( string $pathOrSuffix, bool $isSystemTemp = true ): WorkspaceDB {
		$databasePath = $isSystemTemp ? sys_get_temp_dir() . '/' . $pathOrSuffix : $pathOrSuffix;
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

			// Get namespace for this space to build wiki title
			$spaces = $workspaceDB->getSpaces();
			$namespace = 'SPACE' . $spaceId; // Default fallback
			foreach ( $spaces as $space ) {
				if ( $space['space_id'] === $spaceId ) {
					$namespace = $space['namespace_prefix'];
					break;
				}
			}

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

			// Get the namespace prefix for this space to build wiki titles and interwiki prefix
			$spaces = $workspaceDB->getSpaces();
			$namespace = 'SPACE' . $spaceId; // Default fallback
			foreach ( $spaces as $space ) {
				if ( $space['space_id'] === $spaceId ) {
					$namespace = $space['namespace_prefix'];
					break;
				}
			}
			$interwikiPrefix = "wiki-" . strtolower( $namespace );

			// Add 10 pages to each space
			for ( $pageNum = 1; $pageNum <= 10; $pageNum++ ) {
				$confluenceTitle = "Page {$pageNum}";
				// Use the actual namespace prefix from the space configuration
				$wikiTitle = $namespace . ':Page_' . $pageNum;
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

				// Track body content IDs for this page
				$this->pageBodyContentIds[$pageId] = $bodyContentIds;

				$workspaceDB->addPage(
					$pageId,
					$spaceId,
					$confluenceTitle,
					$wikiTitle,
					$interwikiPrefix,
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
				// Use the actual namespace prefix from the space configuration
				$wikiTitle = 'Blog:' . $namespace . '/Blog_Post_' . $blogNum;
				$blogPostId = $this->nextBlogPostId++;

				$this->spaceBlogs[$spaceId][] = $blogPostId;

				// Add 2 revisions for this blog post
				$revisionCount = 2;
				$bodyContentIds = [];
				for ( $rev = 1; $rev <= $revisionCount; $rev++ ) {
					$bodyContentIds[] = $this->addBodyContent( $workspaceDB, $blogPostId );
				}

				// Track body content IDs for this blog post
				$this->blogBodyContentIds[$blogPostId] = $bodyContentIds;

				$workspaceDB->addBlogPost(
					$blogPostId,
					$spaceId,
					$confluenceTitle,
					$wikiTitle,
					$interwikiPrefix,
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
					$this->addPageAttachmentToContent( $workspaceDB, $spaceId, $pageId, $filename );
				}
			}

			// Add attachments to blog posts (5 per blog)
			foreach ( $this->spaceBlogs[$spaceId] as $blogPostId ) {
				$attachmentCount = 5;
				for ( $i = 1; $i <= $attachmentCount; $i++ ) {
					$extension = $attachmentExtensions[($blogPostId + $i) % count( $attachmentExtensions )];
					$filename = "blog_attachment_{$blogPostId}_{$i}.{$extension}";
					$this->addBlogPostAttachmentToContent( $workspaceDB, $spaceId, $blogPostId, $filename );
				}
			}

		}
	}

	private function addPageAttachmentToContent(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		int $pageId,
		string $filename
	): void {
		$attachmentId = $this->nextAttachmentId++;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		$workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$filename,
			$extension,
			$pageId,
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
			$pageId,
			$filename,
			$filename
		);
	}

	private function addBlogPostAttachmentToContent(
		WorkspaceDB $workspaceDB,
		int $spaceId,
		int $blogPostId,
		string $filename
	): void {
		$attachmentId = $this->nextAttachmentId++;
		$extension = pathinfo( $filename, PATHINFO_EXTENSION );

		$workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$filename,
			$extension,
			$blogPostId,
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

		$workspaceDB->addBlogPostAttachment(
			$attachmentId,
			$blogPostId,
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

	private function seedBodyContentBodies( WorkspaceDB $workspaceDB ): void {
		// Generate and populate body content for all pages and blogs with cross-space links
		for ( $spaceId = 1; $spaceId <= 4; $spaceId++ ) {
			// Populate page body contents
			foreach ( $this->spacePages[$spaceId] as $index => $pageId ) {
				$pageNum = $index + 1;
				// Get all body content IDs for this page from tracking
				if ( !isset( $this->pageBodyContentIds[$pageId] ) ) {
					continue;
				}
				$bodyContentIds = $this->pageBodyContentIds[$pageId];

				foreach ( $bodyContentIds as $bodyContentId ) {
					// Generate XML content with cross-space links
					$xmlContent = $this->generatePageXmlWithLinks( $spaceId, $pageNum );
					$workspaceDB->addBodyContentBody( $bodyContentId, $xmlContent );
				}
			}

			// Populate blog body contents
			foreach ( $this->spaceBlogs[$spaceId] as $index => $blogPostId ) {
				$blogNum = $index + 1;
				// Get all body content IDs for this blog from tracking
				if ( !isset( $this->blogBodyContentIds[$blogPostId] ) ) {
					continue;
				}
				$bodyContentIds = $this->blogBodyContentIds[$blogPostId];

				foreach ( $bodyContentIds as $bodyContentId ) {
					// Generate XML content with cross-space links
					$xmlContent = $this->generateBlogXmlWithLinks( $spaceId, $blogNum );
					$workspaceDB->addBodyContentBody( $bodyContentId, $xmlContent );
				}
			}
		}
	}

	private function generatePageXmlWithLinks( int $spaceId, int $pageNum ): string {
		$xml = '<ac:root xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">' . "\n";
		$xml .= '<p>Page ' . $pageNum . ' of SPACE' . $spaceId . '</p>' . "\n";
		$xml .= '<h2>Links to other pages and blogs:</h2>' . "\n";

		// Add links to other pages in same space
		for ( $i = 1; $i <= 10; $i++ ) {
			if ( $i !== $pageNum ) {
				$xml .= '<p><ac:link><ri:page ri:content-title="Page ' . $i . '" />';
				$xml .= '<ac:plain-text-link-body><![CDATA[Link to Page ' . $i . ' in SPACE' . $spaceId . ']]></ac:plain-text-link-body>';
				$xml .= '</ac:link></p>' . "\n";
			}
		}

		// Add links to other spaces
		for ( $otherSpace = 1; $otherSpace <= 4; $otherSpace++ ) {
			if ( $otherSpace !== $spaceId ) {
				$xml .= '<p><ac:link><ri:page ri:content-title="Page 1" ri:space-key="SPACE' . $otherSpace . '" />';
				$xml .= '<ac:plain-text-link-body><![CDATA[Link to Page 1 in SPACE' . $otherSpace . ']]></ac:plain-text-link-body>';
				$xml .= '</ac:link></p>' . "\n";
			}
		}

		// Add links to blogs in same space
		for ( $blogNum = 1; $blogNum <= 5; $blogNum++ ) {
			$xml .= '<p><ac:link><ri:page ri:content-title="Blog Post ' . $blogNum . '" />';
			$xml .= '<ac:plain-text-link-body><![CDATA[Link to Blog Post ' . $blogNum . ' in SPACE' . $spaceId . ']]></ac:plain-text-link-body>';
			$xml .= '</ac:link></p>' . "\n";
		}

		// Add link to a blog in another space
		if ( $spaceId < 4 ) {
			$otherSpace = $spaceId + 1;
			$xml .= '<p><ac:link><ri:page ri:content-title="Blog Post 1" ri:space-key="SPACE' . $otherSpace . '" />';
			$xml .= '<ac:plain-text-link-body><![CDATA[Link to Blog Post 1 in SPACE' . $otherSpace . ']]></ac:plain-text-link-body>';
			$xml .= '</ac:link></p>' . "\n";
		}

		$xml .= '</ac:root>' . "\n";
		return $xml;
	}

	private function generateBlogXmlWithLinks( int $spaceId, int $blogNum ): string {
		$xml = '<ac:root xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">' . "\n";
		$xml .= '<p>Blog Post ' . $blogNum . ' of SPACE' . $spaceId . '</p>' . "\n";
		$xml .= '<h2>Links from blog:</h2>' . "\n";

		// Add links to pages in same space
		for ( $pageNum = 1; $pageNum <= 5; $pageNum++ ) {
			$xml .= '<p><ac:link><ri:page ri:content-title="Page ' . $pageNum . '" />';
			$xml .= '<ac:plain-text-link-body><![CDATA[Link to Page ' . $pageNum . ' in SPACE' . $spaceId . ']]></ac:plain-text-link-body>';
			$xml .= '</ac:link></p>' . "\n";
		}

		// Add links to other blogs in same space
		for ( $otherBlog = 1; $otherBlog <= 5; $otherBlog++ ) {
			if ( $otherBlog !== $blogNum ) {
				$xml .= '<p><ac:link><ri:page ri:content-title="Blog Post ' . $otherBlog . '" />';
				$xml .= '<ac:plain-text-link-body><![CDATA[Link to Blog Post ' . $otherBlog . ' in SPACE' . $spaceId . ']]></ac:plain-text-link-body>';
				$xml .= '</ac:link></p>' . "\n";
			}
		}

		// Add links to pages in other spaces
		if ( $spaceId < 4 ) {
			$otherSpace = $spaceId + 1;
			$xml .= '<p><ac:link><ri:page ri:content-title="Page 1" ri:space-key="SPACE' . $otherSpace . '" />';
			$xml .= '<ac:plain-text-link-body><![CDATA[Link to Page 1 in SPACE' . $otherSpace . ']]></ac:plain-text-link-body>';
			$xml .= '</ac:link></p>' . "\n";
		}

		$xml .= '</ac:root>' . "\n";
		return $xml;
	}
}
