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

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface {

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
	}

	/**
	 * @param string $dest
	 * @return void
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 * @param Builder $builder
	 * @param string $name
	 * @return Builder
	 */
	private function writeOutputFile( Builder $builder, string $name ): Builder {
		$name = "output-{$name}.xml";

		$builder->buildAndSave( $this->dest . "/result/{$name}" );

		return new Builder();
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ) {
		/** Add default pages ( e.g. templates) */
		$this->appendDefaultPages( $builder );
		$this->addDefaultFiles();
		$builder = $this->writeOutputFile( $builder, 'default' );

		/** Prepare content pages */
		$bodyContentsToPagesMap = $this->buckets->getBucketData( 'global-body-contents-to-pages-map' );
		$spaceIDHomepagesMap = $this->buckets->getBucketData( 'global-space-id-homepages' );

		$homepageSpaceIDMap = array_flip( $spaceIDHomepagesMap );
		$spaceIdDescriptionIdMap = $this->buckets->getBucketData( 'global-space-id-to-description-id-map' );
		$spaceDescriptionIDBodyIDMap = $this->buckets->getBucketData( 'global-space-description-id-to-body-id-map' );

		$pagesRevisions = $this->buckets->getBucketData( 'global-title-revisions' );
		$filesMap = $this->buckets->getBucketData( 'global-files' );
		$pageAttachmentsMap = $this->buckets->getBucketData( 'global-title-attachments' );

		$bodyContentIDMainpageID = [];
		$pagesToBodyContents = array_flip( $bodyContentsToPagesMap );
		foreach ( $spaceIDHomepagesMap as $spaceId => $homepageId ) {
			if ( !isset( $pagesToBodyContents[$homepageId] ) ) {
				continue;
			}
			$bodyContentsID = $pagesToBodyContents[$homepageId];
			$bodyContentIDMainpageID[$bodyContentsID] = $homepageId;
		}

		/** Sort pages by namespace and skip namespaces or titles by configuration */
		$namespaceTitlesMap = [];
		foreach ( $pagesRevisions as $pageTitle => $pageRevisions ) {
			// Sometimes not all namespaces should be used for the import. To skip this namespaces
			// use this option
			$namespace = $this->getNamespace( $pageTitle );
			if (
				isset( $this->advancedConfig['composer-include-namespace'] )
				&& !in_array( $namespace, $this->advancedConfig['composer-include-namespace'] )
			) {
				$this->output->writeln( "Namespace {$namespace} skipped by configuration" );
				continue;
			}

			// Sometimes titles have contents >256kB which might break the import. To skip this titles
			// use this option
			if (
				isset( $this->advancedConfig['composer-skip-titles'] )
				&& in_array( $pageTitle, $this->advancedConfig['composer-skip-titles'] )
			) {
				$this->output->writeln( "Page {$pageTitle} skipped by configuration" );
				continue;
			}

			if ( !isset( $namespaceTitlesMap[$namespace] ) ) {
				$namespaceTitlesMap[$namespace] = [];
			}
			$namespaceTitlesMap[$namespace] = $pageTitle;
		}

		/** Add pages grouped by namespace */
		foreach ( $namespaceTitlesMap as $namespace => $pageTitle ) {
			$pageRevisions = $pagesRevisions[$pageTitle];

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
			if ( !isset( $this->advancedConfig['include-history'] )
				|| $this->advancedConfig['include-history'] !== true
			) {
				$bodyContentIds = end( $sortedRevisions );
				$timestamp = array_search( $bodyContentIds, $sortedRevisions );
				// Reset sortedRevisions
				$sortedRevisions = [];
				$sortedRevisions[$timestamp] = $bodyContentIds;
			}

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

					// Add space description to homepage
					if ( isset( $bodyContentIDMainpageID[$bodyContentId] ) ) {
						// get homepage id if it is a homepage
						$mainpageID = $bodyContentIDMainpageID[$bodyContentId];
						if ( isset( $homepageSpaceIDMap[$mainpageID] ) ) {
							// get space id
							$spaceID = $homepageSpaceIDMap[$mainpageID];
							if ( isset( $spaceIdDescriptionIdMap[$spaceID] ) ) {
								// get description id
								$descID = $spaceIdDescriptionIdMap[$spaceID];
								if ( isset( $spaceDescriptionIDBodyIDMap[$descID] ) ) {
									// get description id
									$descBodyID = $spaceDescriptionIDBodyIDMap[$descID];
									$description = $this->workspace->getConvertedContent( $descBodyID );
									$pageContent .= "[[Space description::$description]]\n";
								}
							}
						}
					}
				}

				$builder->addRevision( $pageTitle, $pageContent, $timestamp );

				// Append attachments
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

			$builder = $this->writeOutputFile( $builder, $namespace );
		}

		$this->customBuckets->saveToWorkspace( $this->workspace );
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
	 * @param Builder $builder
	 * @return void
	 */
	private function appendDefaultPages( Builder $builder ) {
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

			$builder->addRevision( $wikiPageName, $wikiText );
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

}
