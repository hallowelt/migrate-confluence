<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Label;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Labelling;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements LoggerAwareInterface, IOutputAwareInterface, IDestinationPathAware {

	private const NS_BLOG_NAME = 'Blog';

	/** @var string */
	private string $dest = '';

	/** @var LoggerInterface|NullLogger */
	private LoggerInterface|NullLogger $logger;

	/** @var Output|null */
	private ?Output $output = null;

	/** @var SplFileInfo */
	private SplFileInfo $file;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/**
	 *
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->logger = new NullLogger();
	}

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @return void
	 */
	private function initWorkspaceDB(): void {
		$this->workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
	}

	/**
	 * @return void
	 */
	private function initMigrationConfig(): void {
		$advancedConfig = [];
		if ( isset( $this->config['config'] ) ) {
			$advancedConfig = $this->config['config'];
		}
		$this->migrationConfig = new MigrationConfig( $advancedConfig );
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		$this->file = $file;
		if ( $this->file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$this->initMigrationConfig();
		$this->initWorkspaceDB();

		$result = parent::analyze( $file );

		// Perform validity checks
		$this->checkTitles();

		return $result;
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [
			'BodyContent' => new BodyContents( $this->workspaceDB ),
			'Space' => new Spaces( $this->workspaceDB, $this->migrationConfig ),
			'SpaceDescription' => new SpaceDescription( $this->workspaceDB, $this->migrationConfig ),
			'Page' => new Page( $this->workspaceDB, $this->migrationConfig ),
			'BlogPost' => new BlogPost( $this->workspaceDB, $this->migrationConfig ),
			'Attachment' => new Attachments( $this->workspaceDB, $this->migrationConfig, $this->file->getPath() ),
			'Comment' => new Comments( $this->workspaceDB ),
			'Label' => new Label( $this->workspaceDB ),
			'Labelling' => new Labelling( $this->workspaceDB ),
			'ContentProperty' => new ContentProperty( $this->workspaceDB ),
			'ConfluenceUserImpl' => new Users( $this->workspaceDB ),
		];
	}

	/**
	 * @param array $processors
	 * @return void
	 */
	private function initProcessors( array $processors ): void {
		foreach ( $processors as $processor ) {
			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->setOutput( $this->output );
				$processor->setLogger( $this->logger );
			}
		}
	}

	/**
	 * @param array $processors
	 * @return void
	 */
	private function processFile( array $processors ): void {
		$this->initProcessors( $processors );

		$xmlReader = new XMLReader();
		$xmlReader->open( $this->file->getPathname() );

		$read = $xmlReader->read();
		while ( $read ) {
			if ( strtolower( $xmlReader->name ) !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( isset( $processors[$class] ) ) {
				$processor = $processors[$class];
			}

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->execute( $xmlReader );
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doAnalyze( SplFileInfo $file ): bool {
		$this->output->writeln( "\nAnalyze data:" );
		$processors = $this->getProcessors();
		$this->processFile( $processors );

		// TODO: Create fallback to run xmlreader only once
		$this->updateBodyContentIdsFallback();
		$this->updatePageTableWithWikiTitle();
		$this->updateBlogPostTableWithWikiTitle();
		$this->updatePageAttachmentTable();

		// TODO: Update missing table entries (fallbacks) for e. g. Spacedescription

		return true;
		
	}

	/**
	 * @return void
	 */
	private function updateBodyContentIdsFallback(): void {	
		// Update pages table
		$pages = $this->workspaceDB->getPages();
		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'], $page['body_content_ids'] ) ) {
				continue;
			}
			
			$pageId = (int)$page['page_id'];
			$bodyContentIds = json_decode( $page['body_content_ids'], true );
			
			// Check if body_content_ids is empty
			if ( empty( $bodyContentIds ) || $bodyContentIds === null ) {
				$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $pageId );
				if ( !empty( $foundIds ) ) {
					$this->output->writeln( "Updated body_content_ids for page ID $pageId with IDs: " . implode( ', ', $foundIds ) );
					$this->workspaceDB->updatePageBodyContentIds( $pageId, $foundIds );
				}
				
			}
		}
		
		// Update blog_posts table
		$blogPosts = $this->workspaceDB->getBlogPosts();
		foreach ( $blogPosts as $blogPost ) {
			if ( !isset( $blogPost['page_id'], $blogPost['body_content_ids'] ) ) {
				continue;
			}
			
			$pageId = (int)$blogPost['page_id'];
			$bodyContentIds = json_decode( $blogPost['body_content_ids'], true );
			
			// Check if body_content_ids is empty
			if ( empty( $bodyContentIds ) || $bodyContentIds === null ) {
				$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $pageId );
				if ( !empty( $foundIds ) ) {
					$this->output->writeln( "Updated body_content_ids for blog post ID $pageId with IDs: " . implode( ', ', $foundIds ) );
					$this->workspaceDB->updateBlogPostBodyContentIds( $pageId, $foundIds );
				}
			}
		}
		
		// Update comments table
		$comments = $this->workspaceDB->getComments();
		foreach ( $comments as $comment ) {
			if ( !isset( $comment['comment_id'], $comment['body_content_ids'] ) ) {
				continue;
			}
			
			$commentId = (int)$comment['comment_id'];
			$bodyContentIds = json_decode( $comment['body_content_ids'], true );
			
			// Check if body_content_ids is empty
			if ( empty( $bodyContentIds ) || $bodyContentIds === null ) {
				$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $commentId );
				if ( !empty( $foundIds ) ) {
					$this->output->writeln( "Updated body_content_ids for comment ID $commentId with IDs: " . implode( ', ', $foundIds ) );
					$this->workspaceDB->updateCommentBodyContentIds( $commentId, $foundIds );
				}
			}
		}
		
		// Update spaces_descriptions table
		$spaceDescriptions = $this->workspaceDB->getSpaceDescriptions();
		foreach ( $spaceDescriptions as $spaceDesc ) {
			if ( !isset( $spaceDesc['space_description_id'], $spaceDesc['body_content_ids'] ) ) {
				continue;
			}
			
			$spaceDescriptionId = (int)$spaceDesc['space_description_id'];
			$bodyContentIds = json_decode( $spaceDesc['body_content_ids'], true );
			
			// Check if body_content_ids is empty
			if ( empty( $bodyContentIds ) || $bodyContentIds === null ) {
				$foundIds = $this->workspaceDB->getBodyContentIdsForContentId( $spaceDescriptionId );
				if ( !empty( $foundIds ) ) {
					$this->output->writeln( "Updated body_content_ids for space description ID $spaceDescriptionId with IDs: " . implode( ', ', $foundIds ) );
					$this->workspaceDB->updateSpaceDescriptionBodyContentIds( $spaceDescriptionId, $foundIds );
				}
			}
		}
	}

	/**
	 * @return void
	 */
	private function updatePageAttachmentTable(): void {
		$pageIdToWikiTitleMap = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			if ( !isset( $page['page_id'], $page['wiki_title'], $page['content_status'] ) ) {
				continue;
			}
			if ( $page['content_status'] !== 'current' || $page['wiki_title'] === '' ) {
				continue;
			}
			$pageIdToWikiTitleMap[(int)$page['page_id']] = (string)$page['wiki_title'];
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			return;
		}

		$filenameBuilder = new FilenameBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->config
		);

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if ( !isset(
				$attachment['attachment_id'],
				$attachment['space_id'],
				$attachment['filename'],
				$attachment['container_id'],
				$attachment['content_status']
			) ) {
				continue;
			}

			if ( $attachment['content_status'] !== 'current' ) {
				continue;
			}

			$pageId = (int)$attachment['container_id'];
			if ( !isset( $pageIdToWikiTitleMap[$pageId] ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];
			$pageWikiTitle = '';

			$short = false;
			try {
				$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$pageWikiTitle
				);
			} catch ( Exception $ex ) {
				try {
					$shortPageWikiTitle = basename( $pageWikiTitle );
					$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
						$attachmentSpaceId,
						$attachmentOrigFilename,
						$shortPageWikiTitle
					);
					$short = true;
				} catch ( Exception $fallbackEx ) {
					$this->logger->warning(
						'Could not build target filename for attachment ' . $attachmentId . ': '
						. $fallbackEx->getMessage()
					);
					$this->workspaceDB->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not build target filename for attachment $attachmentId: "
						. $fallbackEx->getMessage()
					);
					continue;
				}
			}

			// Uncollide file title
			$exists = $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle );
			$counter = 1;
			while ( $exists ) {
				if ( !$short )  {
					$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
						$attachmentSpaceId,
						$attachmentOrigFilename,
						"-(" . (string)$counter . ")"
					);
				} else {
					$shortPageWikiTitle = basename( $pageWikiTitle );
					$attatchmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
						$attachmentSpaceId,
						$attachmentOrigFilename,
						"-(" . (string)$counter . ")"
					);
				}

				$exists = $this->workspaceDB->checkPageAttachmentWikiTitleExists( $attatchmentWikiTitle );
				$counter++;
			}

			$file = new SplFileInfo( $attatchmentWikiTitle );
			if ( $file->getExtension() === '' || strlen( $file->getExtension() ) > 10 ) {
				$attatchmentWikiTitle .= '.unknown';
			}

			$this->workspaceDB->addPageAttachment(
				$attachmentId,
				$pageId,
				$attachment['filename'],
				$attatchmentWikiTitle
			);
		}
	}

	/**
	 * @return void
	 */
	private function updatePageTableWithWikiTitle(): void {
		$titleBuilder = new TitleBuilder(
			$this->workspaceDB->getMapSpaceIdToPrefix(),
			$this->workspaceDB->getMapSpaceIdToHomepageId(),
			$this->workspaceDB->getMapPageIdtoParentPageId(),
			$this->workspaceDB->getMapPageIdToConfluenceTitle(),
			$this->migrationConfig->getMainPageName()
		);

		$pages = $this->workspaceDB->getPages();
		$pageIdToWikiTitleMap = [];
		foreach ( $pages as $page ) {
			if ( !isset( $page['page_id'], $page['space_id'], $page['confluence_title'], $page['content_status'] ) ) {
				continue;
			}

			// Create a wiki page title only for current page versions.
			// This is needed to avoid creating wiki titles for deleted pages or old page versions.
			if ( $page['content_status'] !== 'current' ) {
				continue;
			}

			$pageId = (int)$page['page_id'];
			$spaceId = (int)$page['space_id'];
			$confluenceTitle = (string)$page['confluence_title'];

			try {
				$wikiTitle = $titleBuilder->buildTitle( $spaceId, $pageId, $confluenceTitle );
				$pageIdToWikiTitleMap[$pageId] = $wikiTitle;
			} catch ( Exception $ex ) {
				$this->logger->warning(
					'Could not build wiki title for page ' . $pageId . ': ' . $ex->getMessage()
				);
				$this->workspaceDB->addLogEntry(
					'warning',
					'analyze',
					__CLASS__,
					"Could not build wiki title for page $pageId: " . $ex->getMessage()
				);
			}
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $pageIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedPageIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $pageIdToWikiTitleMap );

		foreach ( $compressedPageIdToWikiTitleMap as $pageId => $wikiTitle ) {
			$this->workspaceDB->updatePageWikiTitle( (int)$pageId, $wikiTitle );
		}
	}

	/**
	 * @return void
	 */
	private function updateBlogPostTableWithWikiTitle(): void {
		$spaceIdToSpaceKeyMap = $this->workspaceDB->getMapSpaceIdToKey();
		$blogPosts = $this->workspaceDB->getBlogPosts();
		$pageIdToWikiTitleMap = [];

		foreach ( $blogPosts as $blogPost ) {
			if ( !isset( $blogPost['page_id'], $blogPost['space_id'], $blogPost['confluence_title'], $blogPost['content_status'] ) ) {
				continue;
			}

			if ( $blogPost['content_status'] !== 'current' ) {
				continue;
			}

			$pageId = (int)$blogPost['page_id'];
			$spaceId = (int)$blogPost['space_id'];
			$confluenceTitle = (string)$blogPost['confluence_title'];

			if ( !isset( $spaceIdToSpaceKeyMap[$spaceId] ) ) {
				continue;
			}

			$spaceKey = $spaceIdToSpaceKeyMap[$spaceId];
			$blogName = self::NS_BLOG_NAME;
			$titleBuilder = new TitleBuilder( [ $spaceId => "$blogName:$spaceKey/" ], [], [], [] );

			try {
				$wikiTitle = $titleBuilder->buildTitle( $spaceId, $pageId, $confluenceTitle );
				$pageIdToWikiTitleMap[$pageId] = $wikiTitle;
			} catch ( Exception $ex ) {
				$this->logger->warning(
					'Could not build wiki title for blog post ' . $pageId . ': ' . $ex->getMessage()
				);
			}
		}

		if ( $pageIdToWikiTitleMap === [] ) {
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $pageIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedPageIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $pageIdToWikiTitleMap );

		foreach ( $compressedPageIdToWikiTitleMap as $pageId => $wikiTitle ) {
			$this->workspaceDB->updateBlogPostWikiTitle( (int)$pageId, $wikiTitle );
		}
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		$titles = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$title = '';
			$pageId = $page['page_id'];
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
				$title = (string)$page['wiki_title'];
			} elseif ( isset( $page['confluence_title'] ) ) {
				$title = (string)$page['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$title = '';
			$pageId = $blogPost['page_id'];
			if ( isset( $blogPost['wiki_title'] ) && $blogPost['wiki_title'] !== '' ) {
				$title = (string)$blogPost['wiki_title'];
			} elseif ( isset( $blogPost['confluence_title'] ) ) {
				$title = (string)$blogPost['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		$invalidTitles = false;

		$validityChecker = new TitleValidityChecker();

		foreach ( $titles as $pageId => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title ens with invalid character' );
			}
			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains multiple collons' );
					$invalidTitles = true;
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Invalid namespace character detected' );
					$invalidTitles = true;
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains to many characters (>256)' );
					$invalidTitles = true;
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains to many characters (>256)' );
					$invalidTitles = true;
				}
			}
		}

		$invalidAttachments = false;
		$pageAttachments = $this->workspaceDB->getPageAttachments();
		foreach ( $pageAttachments as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$wikiTitle = $attachment['target_attachment_filename'];
			if ( !$validityChecker->hasValidLength( $wikiTitle ) ) {
				$this->workspaceDB->addInvalidTitle( $attachmentId, $wikiTitle, 'Attachment title contains to many characters (>256)' );
				$invalidAttachments = true;
			}
		}

		if ( !empty( $this->workspaceDB->getLogEntriesForStep( 'analyze' ) ) ) {
			$this->output->writeln( "\n\nWARNINGS / ERRORS:\n" );
			$this->output->writeln(
				"\nPlease check logging table in workspaceDB for details about invalid titles and filenames\n\n"
			);
		}

		if ( $invalidTitles ) {
			$this->output->writeln( "\n\INVALID PAGE TITLES DETECTED:\n" );
			$this->output->writeln(
				"\nPlease check invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( $invalidAttachments ) {
			$this->output->writeln( "\n\INVALID ATTACHMENT TITLES DETECTED:\n" );
			$this->output->writeln(
				"\nPlease check invalid_attachment_titles table in workspaceDB for details\n\n"
			);
		}
	}
}
