<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use Dom\Comment;
use Exception;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\Processor\AttachmentFallback;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BlogPost;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Comments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperties;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ContentProperty;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ParentBlogPosts;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements LoggerAwareInterface, IOutputAwareInterface {

	private const NS_BLOG_NAME = 'Blog';

	/** @var DataBuckets */
	private DataBuckets $customBuckets;

	/** @var LoggerInterface|NullLogger */
	private LoggerInterface|NullLogger $logger;

	/** @var Output|null */
	private ?Output $output = null;

	/** @var SplFileInfo */
	private SplFileInfo $file;

	/** @var ConfigDB */
	private ConfigDB $configDB;

	/** @var WorkspaceDB */
	private $workspaceDB;

	/**
	 *
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );
		$this->customBuckets = new DataBuckets( [
			'warning-analyze-invalid-namespaces',
			'warning-analyze-invalid-titles',
			'warning-analyze-invalid-filenames',
		] );

		$this->logger = new NullLogger();

		$this->initConfigDB();
		$this->workspaceDB = new WorkspaceDB( '/app/data/development/workspace/workspace.sql' );
	}

	/**
	 * @return void
	 */
	private function initConfigDB(): void {
		$advancedConfig = [];
		if ( isset( $this->config['config'] ) ) {
			$advancedConfig = $this->config['config'];
		}
		$this->configDB = new ConfigDB( '/app/data/development/workspace/config.sql' );
		$this->configDB->populateConfigTables( $advancedConfig );
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

		$this->customBuckets->loadFromWorkspace( $this->workspace );
		$result = parent::analyze( $file );

		// Perform validity checks
		$this->checkTitles();

		// Save buckets
		$this->customBuckets->saveToWorkspace( $this->workspace );
		return $result;
	}

	/**
	 * @return array
	 */
	private function getPreProcessors(): array {
		return [
			'Space' => new Spaces( $this->configDB, $this->workspaceDB ),
			'SpaceDescription' => new SpaceDescription( $this->configDB, $this->workspaceDB ),
			'BodyContent' => new BodyContents( $this->configDB, $this->workspaceDB ),
			'Page' => new Page( $this->configDB, $this->workspaceDB ),
			'BlogPost' => new BlogPost( $this->configDB, $this->workspaceDB ),
			'Attachment' => new Attachments( $this->file, $this->configDB, $this->workspaceDB  ),
			'ConfluenceUserImpl' => new Users( $this->configDB, $this->workspaceDB ),
			'ContentProperty' => new ContentProperty( $this->configDB, $this->workspaceDB ),
			'Comment' => new Comments( $this->configDB, $this->workspaceDB ),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [];
	}

	/**
	 * @return array
	 */
	private function getPostProcessors(): array {
		return [
			'Attachment' => new AttachmentFallback()
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

		#$test= $this->workspaceDB->getMapPageIdToConfluenceTitle();
		#var_dump( $test );
		#$test= $this->workspaceDB->getMapSpaceIdToPrefix();
		#var_dump( $test );
		$test= $this->workspaceDB->getMapSpaceIdToHomepageId();
		var_dump( $test );

		$this->updatePageTableWithWikiTitle();
		$this->updateBlogPostTableWithWikiTitle();

		return true;
		
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
			$this->configDB->getMainPageName()
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

		$hasInvalidTitles = false;
		$hasInvalidNamespaces = false;
		foreach ( array_keys( $titles ) as $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->customBuckets->addData(
					'warning-analyze-invalid-titles',
					'invalid_ending', $title,
					true, true
				);
				$hasInvalidTitles = true;
			}
			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->customBuckets->addData(
						'warning-analyze-invalid-titles',
						'multiple_collons', $title,
						true, true
					);
					$hasInvalidTitles = true;
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->customBuckets->addData(
						'warning-analyze-invalid-namespaces',
						'invalid_char', $namespace,
						true, true
					);
					$hasInvalidNamespaces = true;
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->customBuckets->addData(
						'warning-analyze-invalid-titles',
						'length', $title,
						true, true
					);
					$hasInvalidTitles = true;
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->customBuckets->addData(
						'warning-analyze-invalid-titles',
						'length', $title,
						true, true
					);
					$hasInvalidTitles = true;
				}
			}
		}

		$files = $this->buckets->getBucketData( 'global-files' );
		$hasInvalidFilenames = false;
		foreach ( $files as $title => $paths ) {
			if ( !$validityChecker->hasValidLength( $title ) ) {
				$this->customBuckets->addData(
					'warning-analyze-invalid-filenames',
					'length', $title,
					true, true
				);
				$hasInvalidFilenames = true;
			}
		}

		if ( $hasInvalidNamespaces === true || $hasInvalidTitles === true || $hasInvalidFilenames === true ) {
			$this->output->writeln( "\n\nWarning:\n" );

			if ( $hasInvalidNamespaces === true ) {
				$this->output->writeln( ' - Analyze process found invalid namespaces' );
			}

			if ( $hasInvalidTitles === true ) {
				$this->output->writeln( ' - Analyze process found invalid titles' );
			}

			if ( $hasInvalidFilenames === true ) {
				$this->output->writeln( ' - Analyze process found invalid filenames' );
			}

			$this->output->writeln(
				"\nPlease check"
			);
			$this->output->writeln(
				"\n - \"warning-analyze-invalid-namespaces.php\""
			);
			$this->output->writeln(
				"\n - \"warning-analyze-invalid-titles.php\""
			);
			$this->output->writeln(
				"\n - \"warning-analyze-invalid-filenames.php\""
			);
			$this->output->writeln(
				"\nbefore continuing with extract step"
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
