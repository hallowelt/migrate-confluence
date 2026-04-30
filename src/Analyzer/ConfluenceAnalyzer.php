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
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements LoggerAwareInterface, IOutputAwareInterface {

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
	 * @return void
	 */
	private function compressLongTitles(): void {
		// compress title lenght
		$titleCompressor = new TitleCompressor();

		// Merge page and blog post titles so long blog post titles are also compressed
		$pageIdToTitlesMap = $this->data['analyze-page-id-to-title-map'];
		$blogPostIdToTitlesMap = $this->data['analyze-blogpost-id-to-title-map'];
		$compressedTitlesMap = $titleCompressor->execute(
			array_merge( $pageIdToTitlesMap, $blogPostIdToTitlesMap )
		);

		$this->data['analyze-orig-title-compressed-title-map'] = $compressedTitlesMap;

		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );

		// pages-titles-map
		$analyzePagesTitlesMap = $this->data['analyze-pages-titles-map'];
		$compressedPagesTitlesMap = $applyCompressedTitles->toMapValues( $analyzePagesTitlesMap );
		ksort( $compressedPagesTitlesMap );

		$this->data['global-pages-titles-map'] = $compressedPagesTitlesMap;

		// blogposts-titles-map
		$analyzeBlogPostsTitlesMap = $this->data['analyze-blogposts-titles-map'];
		$compressedBlogPostsTitlesMap = $applyCompressedTitles->toMapValues( $analyzeBlogPostsTitlesMap );
		ksort( $compressedBlogPostsTitlesMap );

		$this->data['global-blogposts-titles-map'] = $compressedBlogPostsTitlesMap;

		// page-id-to-titles
		$analyzePageIdToTitleMap = $this->data['analyze-page-id-to-title-map'];
		$compressedPageIdToTitleMap = $applyCompressedTitles->toMapValues( $analyzePageIdToTitleMap );
		ksort( $compressedPageIdToTitleMap );

		$this->data['global-page-id-to-title-map'] = $compressedPageIdToTitleMap;

		// blogpost-id-to-titles
		$analyzeBlogPostIdToTitleMap = $this->data['analyze-blogpost-id-to-title-map'];
		$compressedBlogPostIdToTitleMap = $applyCompressedTitles->toMapValues( $analyzeBlogPostIdToTitleMap );
		ksort( $compressedBlogPostIdToTitleMap );

		$this->data['global-blogpost-id-to-title-map'] = $compressedBlogPostIdToTitleMap;

		// title-revisions
		$analyzeTitleRevisionsMap = $this->data['analyze-title-revisions'];
		$compressedTitleRevison = $applyCompressedTitles->toMapKeys( $analyzeTitleRevisionsMap );
		ksort( $compressedTitleRevison );

		$this->data['global-title-revisions'] = $compressedTitleRevison;
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

		return true;
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		$titlesMap = $this->data['global-title-revisions'];

		$validityChecker = new TitleValidityChecker();

		$hasInvalidTitles = false;
		$hasInvalidNamespaces = false;
		foreach ( $titlesMap as $title => $revisons ) {
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
