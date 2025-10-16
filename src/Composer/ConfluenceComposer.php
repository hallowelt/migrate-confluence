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
	private $dataBuckets;

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

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->dataBuckets = new DataBuckets( [
			'space-id-homepages',
			'space-id-to-description-id-map',
			'space-description-id-to-body-id-map',
			'body-contents-to-pages-map',
			'title-attachments',
			'title-revisions',
			'files',
			'additional-files'
		] );

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );

		$this->dataBuckets->loadFromWorkspace( $this->workspace );

		if ( isset( $config['config'] ) ) {
			$this->advancedConfig = $config['config'];
		}
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ) {
		$this->output = $output;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ) {
		$this->appendDefaultPages( $builder );
		$this->addDefaultFiles();

		$bodyContentsToPagesMap = $this->dataBuckets->getBucketData( 'body-contents-to-pages-map' );
		$spaceIDHomepagesMap = $this->dataBuckets->getBucketData( 'space-id-homepages' );

		$homepageSpaceIDMap = array_flip( $spaceIDHomepagesMap );
		$spaceIDDescriptionIDMap = $this->dataBuckets->getBucketData( 'space-id-to-description-id-map' );
		$spaceDescriptionIDBodyIDMap = $this->dataBuckets->getBucketData( 'space-description-id-to-body-id-map' );

		$pagesRevisions = $this->dataBuckets->getBucketData( 'title-revisions' );
		$filesMap = $this->dataBuckets->getBucketData( 'files' );
		$pageAttachmentsMap = $this->dataBuckets->getBucketData( 'title-attachments' );

		$bodyContentIDMainpageID = [];
		$pagesToBodyContents = array_flip( $bodyContentsToPagesMap );
		foreach ( $spaceIDHomepagesMap as $spaceID => $homepageID ) {
			$bodyContentsID = $pagesToBodyContents[$homepageID];
			$bodyContentIDMainpageID[$bodyContentsID] = $homepageID;
		}

		foreach ( $pagesRevisions as $pageTitle => $pageRevision ) {
			$this->output->writeln( "\nProcessing: $pageTitle\n" );

			$pageRevisionData = explode( '@', $pageRevision[0] );

			$timestamp = explode( '-', $pageRevisionData[1] )[1];

			$bodyContentIds = $pageRevisionData[0];
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
						if ( isset( $spaceIDDescriptionIDMap[$spaceID] ) ) {
							// get description id
							$descID = $spaceIDDescriptionIDMap[$spaceID];
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

			$namespace = $this->getNamespace( $pageTitle );
			if (
				isset( $this->advancedConfig['skip-namespace'] ) 
				&& in_array( $namespace, $this->advancedConfig['skip-namespace'] )
			) {
				$this->output->writeln( "Page {$pageTitle} skipped by configuration" );
				continue;
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

		$this->customBuckets->saveToWorkspace( $this->workspace );
	}

	/**
	 * @param string $title
	 * @return string
	 */
	private function getNamespace( string $title ): string {
		$collonPos = strpos( $title, ':' );
		if ( !$collonPos ) {
			return '';
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
