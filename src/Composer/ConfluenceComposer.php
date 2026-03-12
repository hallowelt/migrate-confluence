<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/**
	 * @var DataBuckets
	 */
	private $customBuckets;

	/**
	 * @var Output
	 */
	private $output = null;

	/** @var array */
	private $advancedConfig = [];

	/** @var string */
	private $dest = '';

	/** @var Builder */
	private $builder = null;

	/** @var int */
	private $addedRevisions = 0;

	/** @var int */
	private $xmlNumber = 0;

	/** @var int */
	private $limit = 0;

	/** @var bool */
	private $mulitXmlOutputEnabled = false;

	/** @var bool */
	private $multiWikiOutputEnabled = false;

	/**
	 * Config from `wikis` key: wikiName → [ 'spaces' => [ spaceKey => targetNsPrefix ] ]
	 *
	 * @var array
	 */
	private $wikisConfig = [];

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );

		$this->customBuckets->loadFromWorkspace( $this->workspace );

		if ( isset( $config['config'] ) ) {
			$this->advancedConfig = $config['config'];
		}
		if ( isset( $this->advancedConfig['composer-page-per-xml-limit'] ) ) {
			$this->limit = $this->advancedConfig['composer-page-per-xml-limit'];
			$this->mulitXmlOutputEnabled = true;
		}
		if ( isset( $this->advancedConfig['wikis'] ) && is_array( $this->advancedConfig['wikis'] ) ) {
			$this->wikisConfig = $this->advancedConfig['wikis'];
			$this->multiWikiOutputEnabled = true;
		}
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 * @inheritDoc
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ) {
		$this->builder = $builder;

		if ( $this->multiWikiOutputEnabled ) {
			$this->buildXMLMultiWiki();
			return;
		}

		/** Add default pages ( e.g. templates) */
		$this->appendDefaultPages();
		$this->addDefaultFiles();

		/** Add content pages */
		$spaceIdHomepagesMap = $this->buckets->getBucketData(
			'global-space-id-homepages'
		);
		$homepagespaceIdMap = array_flip( $spaceIdHomepagesMap );
		$spaceIdDescriptionIdMap = $this->buckets->getBucketData(
			'global-space-id-to-description-id-map'
		);
		$spaceBodyIdDescriptionIdBodyIDMap = $this->buckets->getBucketData(
			'global-body-content-id-to-space-description-id-map'
		);
		$titleRevisions = $this->buckets->getBucketData(
			'global-title-revisions'
		);

		/** Prepare required maps */
		$bodyContentIdMainpageId = $this->buildMainpageContentMap( $spaceIdHomepagesMap );

		/** Add grouped pages */
		foreach ( $titleRevisions as $pageTitle => $pageRevisions ) {
			if ( $this->skipTitle( $pageTitle ) ) {
				continue;
			}

			$sortedRevisions = $this->sortRevisions( $pageRevisions );
			foreach ( $sortedRevisions as $timestamp => $bodyContentIds ) {
				$bodyContentIdsArr = explode( '/', $bodyContentIds );
				$pageContent = "";
				foreach ( $bodyContentIdsArr as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
						// Skip if no reference to a body content is not set
						continue;
					}
					$this->output->writeln( "Getting '$bodyContentId' body content..." );
					$pageContent .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
					$pageContent .= $this->addSpaceDescriptionToMainPage(
						$bodyContentId,
						$bodyContentIdMainpageId,
						$homepagespaceIdMap,
						$spaceIdDescriptionIdMap,
						array_flip( $spaceBodyIdDescriptionIdBodyIDMap )
					);
				}

				$this->addRevision( $pageTitle, $pageContent, $timestamp );

				// Add attachments
				$this->addTitleAttachments( $pageTitle );
			}
		}

		$this->writeOutputFile();

		$this->customBuckets->saveToWorkspace( $this->workspace );
	}

	/**
	 * @param string $wikiPageName
	 * @param string $wikiText
	 * @return void
	 */
	private function addRevision(
		string $wikiPageName, string $wikiText, string $timestamp = '',
		string $username = '', string $model = '', string $format = ''
	): void {
		$this->builder->addRevision(
			$wikiPageName, $wikiText, $timestamp, $username, $model, $format
		);
		$this->addedRevisions++;

		if ( $this->mulitXmlOutputEnabled ) {
			if ( $this->addedRevisions >= $this->limit ) {
				$this->writeOutputFile();
				$this->addedRevisions = 0;
			}
		}
	}

	/**
	 * @return void
	 */
	private function writeOutputFile(): void {
		$name = "output.xml";
		if ( $this->mulitXmlOutputEnabled ) {
			$this->xmlNumber++;
			$num = (string)$this->xmlNumber;
			$name = "output-{$num}.xml";
		}

		$this->builder->buildAndSave( $this->dest . "/result/{$name}" );
		$this->builder = new Builder();
	}

	/**
	 * Build the full multi-wiki XML output by delegating to MultiWikiComposer.
	 *
	 * @return void
	 */
	private function buildXMLMultiWiki(): void {
		$multiWikiComposer = new MultiWikiComposer(
			$this->wikisConfig,
			$this->buckets,
			$this->workspace,
			$this->advancedConfig,
			$this->output,
			$this->dest,
			$this->customBuckets
		);
		$multiWikiComposer->compose();
	}

	/**
	 * @return void
	 */
	private function appendDefaultPages() {
		$basepath = __DIR__ . '/_defaultpages/';
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			$namespacePrefix = basename( dirname( $file ) );
			$pageName = basename( $file );
			$wikiPageName = "$namespacePrefix:$pageName";
			$wikiText = file_get_contents( $file );

			$this->addRevision( $wikiPageName, $wikiText );
		}
	}

	/**
	 * @return void
	 */
	private function addDefaultFiles() {
		$basepath = __DIR__ . '/_defaultfiles/';
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			$fileName = basename( $file );
			$data = file_get_contents( $file );

			$this->workspace->saveUploadFile( $fileName, $data );
		}
	}

	/**
	 * Sometimes not all namespaces should be used for the import.
	 * To skip this namespaces use this option.
	 *
	 * @param string $pageTitle
	 * @return bool
	 */
	private function skipTitle( string $pageTitle ): bool {
		$namespace = $this->getNamespace( $pageTitle );
		if (
			isset( $this->advancedConfig['composer-skip-namespace'] )
			&& in_array( $namespace, $this->advancedConfig['composer-skip-namespace'] )
		) {
			$this->output->writeln( "Namespace {$namespace} skipped by configuration" );
			return true;
		}

		// Sometimes titles have contents >256kB which might break the import. To skip this titles
		// use this option
		if (
			isset( $this->advancedConfig['composer-skip-titles'] )
			&& in_array( $pageTitle, $this->advancedConfig['composer-skip-titles'] )
		) {
			$this->output->writeln( "Page {$pageTitle} skipped by configuration" );
			return true;
		}
		return false;
	}

	/**
	 * @param string $title
	 * @return string
	 */
	private function getNamespace( string $title ): string {
		$collonPos = strpos( $title, ':' );
		if ( !$collonPos ) {
			return 'NS_MAIN';
		}
		return substr( $title, 0, $collonPos );
	}

	/**
	 * @return bool
	 */
	private function includeHistory(): bool {
		if ( isset( $this->advancedConfig['include-history'] )
			&& $this->advancedConfig['include-history'] !== true
		) {
			return true;
		}

		return false;
	}

	/**
	 * @param string $pageTitle
	 * @return void
	 */
	private function addTitleAttachments( string $pageTitle ): void {
		$pageAttachmentsMap = $this->buckets->getBucketData( 'global-title-attachments' );
		$filesMap = $this->buckets->getBucketData( 'global-files' );

		if ( !empty( $pageAttachmentsMap[$pageTitle] ) ) {
			$this->output->writeln( "\nPage has attachments. Adding them...\n" );

			$attachments = $pageAttachmentsMap[$pageTitle];
			foreach ( $attachments as $attachment ) {
				$this->output->writeln( "Attachment: $attachment" );

				$drawIoFileHandler = new DrawIOFileHandler();

				// We do not need DrawIO data files in our wiki, just PNG image
				if ( $drawIoFileHandler->isDrawIODataFile( $attachment ) ) {
					continue;
				}

				if ( isset( $filesMap[$attachment] ) ) {
					$filePath = $filesMap[$attachment][0];
					$attachmentContent = file_get_contents( $filePath );

					$this->workspace->saveUploadFile( $attachment, $attachmentContent );
					$this->customBuckets->addData( 'title-uploads', $pageTitle, $attachment );
				} else {
					$this->output->writeln( "Attachment file was not found!" );
					$this->customBuckets->addData( 'title-uploads-fail', $pageTitle, $attachment );
				}
			}
		}
	}

	/**
	 * @param array $spaceIdHomepagesMap
	 * @return array
	 */
	private function buildMainpageContentMap( array $spaceIdHomepagesMap ): array {
		$bodyContentsToPagesMap = $this->buckets->getBucketData( 'global-body-content-id-to-page-id-map' );

		$bodyContentIdMainpageId = [];
		$pagesToBodyContents = array_flip( $bodyContentsToPagesMap );
		foreach ( $spaceIdHomepagesMap as $homepageId ) {
			if ( !isset( $pagesToBodyContents[$homepageId] ) ) {
				continue;
			}
			$bodyContentsID = $pagesToBodyContents[$homepageId];
			$bodyContentIdMainpageId[$bodyContentsID] = $homepageId;
		}

		return $bodyContentIdMainpageId;
	}

	/**
	 * @param array $pageRevisions
	 * @return array
	 */
	private function sortRevisions( array $pageRevisions ): array {
		$sortedRevisions = [];
		foreach ( $pageRevisions as $pageRevision ) {
			$pageRevisionData = explode( '@', $pageRevision );
			$bodyContentIds = $pageRevisionData[0];

			$versionTimestamp = explode( '-', $pageRevisionData[1] );
			// $version = $versionTimestamp[0];
			$timestamp = $versionTimestamp[1];

			$sortedRevisions[$bodyContentIds] = $timestamp;
		}

		// Sorting revisions with timestamps
		natsort( $sortedRevisions );
		$sortedRevisions = array_flip( $sortedRevisions );

		// Using history revisions?
		if ( !$this->includeHistory() ) {
			$bodyContentIds = end( $sortedRevisions );
			$timestamp = array_search( $bodyContentIds, $sortedRevisions );
			// Reset sortedRevisions
			$sortedRevisions = [];
			$sortedRevisions[$timestamp] = $bodyContentIds;
		}

		return $sortedRevisions;
	}

	/**
	 * Add space description to homepage
	 *
	 * @param string|int $bodyContentId
	 * @param array $bodyContentIdMainpageId
	 * @param array $homepagespaceIdMap
	 * @param array $spaceIdDescriptionIdMap
	 * @param array $spaceDescriptionIdBodyIdMap
	 * @return string
	 */
	private function addSpaceDescriptionToMainPage(
		$bodyContentId, array $bodyContentIdMainpageId,
		array $homepagespaceIdMap, array $spaceIdDescriptionIdMap,
		array $spaceDescriptionIdBodyIdMap
	): string {
		$pageContent = '';

		if ( isset( $bodyContentIdMainpageId[$bodyContentId] ) ) {
			// get homepage id if it is a homepage
			$mainpageID = $bodyContentIdMainpageId[$bodyContentId];
			if ( isset( $homepagespaceIdMap[$mainpageID] ) ) {
				// get space id
				$spaceId = $homepagespaceIdMap[$mainpageID];
				if ( isset( $spaceIdDescriptionIdMap[$spaceId] ) ) {
					// get description id
					$descId = $spaceIdDescriptionIdMap[$spaceId];
					if ( isset( $spaceDescriptionIdBodyIdMap[$descId] ) ) {
						// get description id
						$descBodyId = $spaceDescriptionIdBodyIdMap[$descId];
						$description = $this->workspace->getConvertedContent( $descBodyId );
						if ( $description !== '' ) {
							$pageContent .= "[[Space description::$description]]\n";
						}
					}
				}
			}
		}

		return $pageContent;
	}
}
