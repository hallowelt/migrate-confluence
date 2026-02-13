<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\AnalyzerBase;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\Processor\AttachmentFallback;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Attachments;
use HalloWelt\MigrateConfluence\Analyzer\Processor\BodyContents;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Page;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Pages;
use HalloWelt\MigrateConfluence\Analyzer\Processor\ParentPages;
use HalloWelt\MigrateConfluence\Analyzer\Processor\SpaceDescription;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Spaces;
use HalloWelt\MigrateConfluence\Analyzer\Processor\Users;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SplFileInfo;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use XMLReader;

class ConfluenceAnalyzer extends AnalyzerBase implements LoggerAwareInterface, IOutputAwareInterface {

	/**
	 * @var DataBuckets
	 */
	private $customBuckets = null;

	/**
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * @var Input
	 */
	private $input = null;

	/**
	 * @var Output
	 */
	private $output = null;

	/**
	 * @var string
	 */
	private $mainpage = 'Main Page';

	/**
	 * @var bool
	 */
	private $extNsFileRepoCompat = false;

	/**
	 * @var array
	 */
	private $advancedConfig = [];

	/** @var array */
	private $includeSpaceKey = [];

	/** @var array */
	private $spacePrefixMap = [];

	/** @var bool */
	private $includeHistory = false;

	/** @var array */
	private $data = [];

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

		$this->setConfigVars();
	}

	/**
	 * @return void
	 */
	private function setConfigVars(): void {
		if ( isset( $this->config['config'] ) ) {
			$this->advancedConfig = $this->config['config'];
		}

		if ( isset( $this->advancedConfig['space-prefix'] ) ) {
			$this->spacePrefixMap = $this->advancedConfig['space-prefix'];
		}

		if ( isset( $this->advancedConfig['ext-ns-file-repo-compat'] ) ) {
			if ( is_bool( $this->advancedConfig['ext-ns-file-repo-compat'] ) ) {
				$this->extNsFileRepoCompat = $this->advancedConfig['ext-ns-file-repo-compat'];
			}
		}

		if ( isset( $this->advancedConfig['mainpage'] ) ) {
			$this->mainpage = $this->advancedConfig['mainpage'];
		}

		if ( isset( $this->advancedConfig['analyzer-include-spacekey'] ) ) {
			$analyzerIncludeSpacekey = $this->advancedConfig['analyzer-include-spacekey'];
			$normalizedAnalyzerIncludeSpacekey = [];
			foreach ( $analyzerIncludeSpacekey as $key ) {
				$normalizedAnalyzerIncludeSpacekey[] = strtolower( $key );
			}
			$this->advancedConfig['analyzer-include-spacekey'] = $normalizedAnalyzerIncludeSpacekey;
			$this->includeSpaceKey = $normalizedAnalyzerIncludeSpacekey;
		}

		if ( isset( $this->advancedConfig['include-history'] ) ) {
			if ( $this->advancedConfig['include-history'] === true ) {
				$this->includeHistory = $this->advancedConfig['include-history'];
			}
		}
	}

	/**
	 * @param LoggerInterface $logger
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * @param Input $input
	 */
	public function setInput( Input $input ) {
		$this->input = $input;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function analyze( SplFileInfo $file ): bool {
		if ( $file->getFilename() !== 'entities.xml' ) {
			return true;
		}

		$keys = [
			'analyze-added-attachment-id',
			'analyze-add-file',
			'analyze-attachment-available-ids',
			'analyze-attachment-id-to-container-content-id-map',
			'analyze-attachment-id-to-content-status-map',
			'analyze-attachment-id-to-orig-filename-map',
			'analyze-attachment-id-to-reference-map',
			'analyze-attachment-id-to-space-id-map',
			'analyze-attachment-id-to-target-filename-map',
			'analyze-body-content-id-to-page-id-map',
			'analyze-orig-title-compressed-title-map',
			'analyze-page-id-to-confluence-key-map',
			'analyze-page-id-to-confluence-title-map',
			'analyze-page-id-to-parent-page-id-map',
			'analyze-page-id-to-title-map',
			'analyze-pages-titles-map',
			'analyze-space-id-to-name-map',
			'analyze-space-id-to-space-key-map',
			'analyze-space-key-to-name-map',
			'analyze-space-name-to-prefix-map',
			'analyze-title-revisions',
			'analyze-title-to-attachment-title',
			'debug-analyze-invalid-titles-attachment-id-to-title',
			'debug-analyze-invalid-titles-page-id-to-title',
			'global-attachment-orig-filename-target-filename-map',
			'global-body-contents-to-pages-map',
			'global-filenames-to-filetitles-map',
			'global-files',
			'global-page-id-to-space-id',
			'global-page-id-to-title-map',
			'global-pages-titles-map',
			'global-space-description-id-to-body-id-map',
			'global-space-details',
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-space-id-to-prefix-map',
			'global-space-key-to-prefix-map',
			'global-title-attachments',
			'global-title-revisions',
			'global-userkey-to-username-map',
			'users',
		];
		foreach ( $keys as $key ) {
			$this->data[$key] = $this->workspace->loadData( $key );
		}

		//$this->customBuckets->loadFromWorkspace( $this->workspace );
		$result = parent::analyze( $file );

		//$this->checkTitles();

		//$this->customBuckets->saveToWorkspace( $this->workspace );
		return $result;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doAnalyze( SplFileInfo $file ): bool {
		$xmlReader = new XMLReader();

		// Process Space and BodyContents objects (needed by other objects)
		$this->output->writeln( "\nPreprocess data:" );

		$preprocessors = [
			'Space' => new Spaces( $this->spacePrefixMap ),
			'SpaceDescription' => new SpaceDescription(),
			'Page' => new ParentPages(),
			'BodyContent' => new BodyContents(),
			'Attachment' => new Attachments( $file ),
			'ConfluenceUserImpl' => new Users(),
		];
		foreach ( $preprocessors as $preprocessor ) {
			if ( $preprocessor instanceof IAnalyzerProcessor ) {
				$preprocessor->setOutput( $this->output );
				$preprocessor->setLogger( $this->logger );
			}
		}

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$objectXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $objectXML );

			$preprocessor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( isset( $preprocessors[$class] ) ) {
				var_dump( $class );
				$preprocessor = $preprocessors[$class];
			}

			if ( $preprocessor instanceof IAnalyzerProcessor ) {
				$preprocessor->setData( $this->data );
				$preprocessor->execute( $objectDom );
				$keys = $preprocessor->getKeys();
				foreach( $keys as $key ) {
					$this->data[$key] = $preprocessor->getData( $key );
				}
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		// Process Page objects (needed by other objects)
		$this->output->writeln( "\nProcess data:" );

		$processors = [
			'Page' => new Page(
				$this->includeSpaceKey,
				$this->mainpage,
				$this->includeHistory
			),
		];
		foreach ( $processors as $processor ) {
			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->setOutput( $this->output );
				$processor->setLogger( $this->logger );
			}
		}

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$nodeXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $nodeXML );

			$processor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( isset( $processors[$class] ) ) {
				//$this->buildPageMaps( $objectDom );
				$processor = $processors[$class];
			}

			if ( $processor instanceof IAnalyzerProcessor ) {
				$processor->setData( $this->data );
				$processor->execute( $objectDom );
				$keys = $processor->getKeys();
				foreach( $keys as $key ) {
					$this->data[$key] = $processor->getData( $key );
				}
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		// compress title lenght
		$titleCompressor = new TitleCompressor();
		//$analyzePagesTitlesMap = $this->customBuckets->getBucketData( 'analyze-pages-titles-map' );
		$analyzePagesTitlesMap = $this->data['analyze-pages-titles-map'];
		$compressedTitlesMap = $titleCompressor->execute( $analyzePagesTitlesMap );
		foreach ( $compressedTitlesMap as $origTitle => $compressedTitle ) {
			/*
			$this->buckets->addData(
				'analyze-orig-title-compressed-title-map',
				$origTitle, $compressedTitle, false, true
			);
			*/
			$this->data['analyze-orig-title-compressed-title-map'][$origTitle] = $compressedTitle;
		}

		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );

		// pages-titles-map
		$compressedPagesTitlesMap = $applyCompressedTitles->toMapValues( $analyzePagesTitlesMap );
		ksort( $compressedPagesTitlesMap );
		foreach ( $compressedPagesTitlesMap as $key => $title ) {
			//$this->buckets->addData( 'global-pages-titles-map', $key, $title, false, true );
			$this->data['global-pages-titles-map'][$key] = $title;
		}

		// page-id-to-titles
		//$analyzePageIdToTitleMap = $this->customBuckets->getBucketData( 'analyze-page-id-to-title-map' );
		$analyzePageIdToTitleMap = $this->data['analyze-page-id-to-title-map'];
		$compressedPageIdToTitleMap = $applyCompressedTitles->toMapValues( $analyzePageIdToTitleMap );
		ksort( $compressedPageIdToTitleMap );
		foreach ( $compressedPageIdToTitleMap as $id => $title ) {
			//$this->buckets->addData( 'global-page-id-to-title-map', $id, $title, false, true );
			$this->data['global-page-id-to-title-map'][$id] = $title;
		}

		// title-revisions
		//$analyzeTitleRevisionsMap = $this->customBuckets->getBucketData( 'analyze-title-revisions' );
		$analyzeTitleRevisionsMap = $this->data['analyze-title-revisions'];
		$compressedTitleRevison = $applyCompressedTitles->toMapKeys( $analyzeTitleRevisionsMap );
		ksort( $compressedTitleRevison );
		foreach ( $compressedTitleRevison as $title => $revisions ) {
			$this->data['global-title-revisions'][$title] = $revisions;
			/*
			if ( is_array( $revisions ) ) {
				foreach ( $revisions as $revision ) {
					$this->addTitleRevision( $title, $revision );
				}
			}
			*/
		}

		// Process title attachments fallback
		$this->output->writeln( "\nPostprocess data:" );

		$postprocessors = [
			'Attachment' => new AttachmentFallback()
		];
		foreach ( $postprocessors as $postprocessor ) {
			if ( $postprocessor instanceof IAnalyzerProcessor ) {
				$postprocessor->setOutput( $this->output );
				$postprocessor->setLogger( $this->logger );
			}
		}

		$xmlReader->open( $file->getPathname() );
		$read = $xmlReader->read();
		while ( $read ) {
			if ( $xmlReader->name !== 'object' ) {
				// Usually all root nodes should be objects.
				$read = $xmlReader->read();
				continue;
			}

			$nodeXML = $xmlReader->readOuterXml();

			$objectDom = new DOMDocument();
			$objectDom->loadXML( $nodeXML );

			$postprocessor = null;
			$class = $xmlReader->getAttribute( 'class' );
			if ( isset( $postprocessor[$class] ) ) {
				//$this->buildPageMaps( $objectDom );
				$postprocessor = $postprocessors[$class];
			}

			if ( $postprocessor instanceof IAnalyzerProcessor ) {
				$postprocessor->setData( $this->data );
				$postprocessor->execute( $objectDom );
				$keys = $postprocessor->getKeys();
				foreach( $keys as $key ) {
					$this->data[$key] = $postprocessor->getData( $key );
				}
			}

			$read = $xmlReader->next();
		}
		$xmlReader->close();

		foreach ( $this->data['analyze-add-file'] as $filename => $reference ) {
			$this->addFile( $filename, $reference );
		}

		foreach ( $this->data as $bucket => $bucketData ) {
			if ( empty( $bucketData ) ) {
				continue;
			}
			$this->workspace->saveData( "{$bucket}.php", $bucketData );
		}

		return true;
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		$pagesTitlesMap = $this->buckets->getBucketData( 'global-pages-titles-map' );

		$validityChecker = new TitleValidityChecker();

		$hasInvalidTitles = false;
		$hasInvalidNamespaces = false;
		foreach ( $pagesTitlesMap as $key => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->customBuckets->addData(
					'warning-analyze-invalid-titles',
					'invalid_ending', $title,
					true, true
				);
				$hasInvalidTitles = true;
			}
			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleCollon( $title ) ) {
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
			if ( $validityChecker->hasValidLength( $title ) ) {
				$this->customBuckets->addData(
					'warning-analyze-invalid-filenames',
					'length', $title,
					true, true
				);
				$hasInvalidFilenames = true;
			}
		}

		if ( $hasInvalidNamespaces === true || $hasInvalidTitles === true ) {
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
	protected function addTitleRevision( $titleText, $contentReference = 'n/a' ) {
		$this->buckets->addData( 'global-title-revisions', $titleText, $contentReference, true, true );
	}

	/**
	 *
	 * @param string $titleText
	 * @param string $attachmentReference
	 * @return void
	 */
	protected function addTitleAttachment( $titleText, $attachmentReference = 'n/a' ) {
		$this->buckets->addData( 'global-title-attachments', $titleText, $attachmentReference );
	}

	/**
	 *
	 * @param string $rawFilename
	 * @param string $attachmentReference
	 * @return void
	 */
	protected function addFile( $rawFilename, $attachmentReference = 'n/a' ) {
		try {
			$filename = $this->getFilename( $rawFilename, $attachmentReference );
			$filename = ( new WindowsFilename( $filename ) ) . '';
		} catch ( InvalidTitleException $ex ) {
			$this->logger->error( $ex->getMessage() );
			return;
		}

		$prefixedFilename = $this->maybePrefixFilename( $filename );

		$this->buckets->addData( 'global-files', $prefixedFilename, $attachmentReference );
	}
}
