<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;
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

		$this->buckets->loadFromWorkspace( $this->workspace );
		$result = parent::analyze( $file );

		// Perform validity checks
		$this->checkTitles();

		// Save buckets
		$this->buckets->saveToWorkspace( $this->workspace );
		return $result;
	}

	/**
	 * @return array
	 */
	private function getPreProcessors(): array {
		return [
			'Space' => new Spaces( $this->workspaceDB, $this->migrationConfig ),
			'SpaceDescription' => new SpaceDescription( $this->workspaceDB ),
			'BodyContent' => new BodyContents( $this->workspaceDB ),
			'ConfluenceUserImpl' => new Users( $this->workspaceDB ),
			'ContentProperty' => new ContentProperty( $this->workspaceDB ),
			'Comment' => new Comments( $this->workspaceDB ),
			'Labelling' => new Labelling( $this->workspaceDB ),
			'Label' => new Label( $this->workspaceDB ),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [
			'Page' => new Page( $this->workspaceDB, $this->migrationConfig ),
			'BlogPost' => new BlogPost( $this->workspaceDB, $this->migrationConfig ),
			'Attachment' => new Attachments( $this->workspaceDB, $this->file->getPath() ),
		];
	}

	/**
	 * @return array
	 */
	private function getPostProcessors(): array {
		return [
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
		// Process Space and BodyContents objects (needed by other objects)
		$this->output->writeln( "\nPreprocess data:" );
		$preprocessors = $this->getPreProcessors();
		$this->processFile( $preprocessors );

		$this->output->writeln( "\nProcess data:" );
		$processors = $this->getProcessors();
		$this->processFile( $processors );

		$this->updatePageTableWithWikiTitle();
		$this->updateBlogPostTableWithWikiTitle();
		$this->updatePageAttachmentTable();

		return true;
		
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
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
				$title = (string)$page['wiki_title'];
			} elseif ( isset( $page['confluence_title'] ) ) {
				$title = (string)$page['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$title] = true;
			}
		}

		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$title = '';
			if ( isset( $blogPost['wiki_title'] ) && $blogPost['wiki_title'] !== '' ) {
				$title = (string)$blogPost['wiki_title'];
			} elseif ( isset( $blogPost['confluence_title'] ) ) {
				$title = (string)$blogPost['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$title] = true;
			}
		}

		$validityChecker = new TitleValidityChecker();

		foreach ( array_keys( $titles ) as $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->buckets->addData(
					'warning-analyze-invalid-titles',
					'invalid_ending', $title,
					true, true
				);
			}
			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->buckets->addData(
						'warning-analyze-invalid-titles',
						'multiple_collons', $title,
						true, true
					);
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->buckets->addData(
						'warning-analyze-invalid-namespaces',
						'invalid_char', $namespace,
						true, true
					);
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->buckets->addData(
						'warning-analyze-invalid-titles',
						'length', $title,
						true, true
					);
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->buckets->addData(
						'warning-analyze-invalid-titles',
						'length', $title,
						true, true
					);
				}
			}
		}

		$files = $this->workspaceDB->getPageAttachments();
		foreach ( $files as $file ) {
			if ( !$validityChecker->hasValidLength( $file['target_attachment_filename'] ) ) {
				$this->workspaceDB->addLogEntry(
					'warning',
					'analyze',
					__METHOD__,
					'Attachment with invalid filename: ' . $file['target_attachment_filename']
				);
			}
		}

		if ( !empty( $this->workspaceDB->getLogEntriesForStep( 'analyze' ) ) ) {
			$this->output->writeln( "\n\nWARNING:\n" );
			$this->output->writeln(
				"\nPlease check logging table in workspaceDB for details about invalid titles and filenames\n\n"
			);
		}
	}

	/**
	 *
	 * @param string $titleText
	 * @param string $contentReference
	 * @return void
	 */
	protected function addTitleRevision( $titleText, $contentReference = 'n/a' ): void {
		$this->buckets->addData( 'global-title-revisions', $titleText, $contentReference, true, true );
	}

	/**
	 *
	 * @param string $titleText
	 * @param string $attachmentReference
	 * @return void
	 */
	protected function addTitleAttachment( $titleText, $attachmentReference = 'n/a' ): void {
		$this->buckets->addData( 'global-title-attachments', $titleText, $attachmentReference );
	}

	/**
	 *
	 * @param string $rawFilename
	 * @param string $attachmentReference
	 * @return void
	 */
	protected function addFile( $rawFilename, $attachmentReference = 'n/a' ): void {
		try {
			$filename = $this->getFilename( $rawFilename, $attachmentReference );
			$filename = ( new WindowsFilename( $filename ) ) . '';
		} catch ( Exception $ex ) {
			$this->logger->error( $ex->getMessage() );
			return;
		}

		$prefixedFilename = $this->maybePrefixFilename( $filename );

		$this->buckets->addData( 'global-files', $prefixedFilename, $attachmentReference );
	}
}
