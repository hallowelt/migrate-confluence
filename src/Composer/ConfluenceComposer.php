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
	 * @var Output
	 */
	private $output = null;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		$this->dataBuckets = new DataBuckets( [
			'title-attachments',
			'title-revisions',
			'files'
		] );

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );

		$this->dataBuckets->loadFromWorkspace( $this->workspace );
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
		$timeStart = microtime( true );

		$this->appendDefaultPages( $builder );
		$this->addDefaultFiles();

		$pagesRevisions = $this->dataBuckets->getBucketData( 'title-revisions' );
		$filesMap = $this->dataBuckets->getBucketData( 'files' );
		$pageAttachmentsMap = $this->dataBuckets->getBucketData( 'title-attachments' );

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
			}

			$builder->addRevision( $pageTitle, $pageContent, $timestamp );

			// Append attachments
			if ( !empty( $pageAttachmentsMap[$pageTitle] ) ) {
				$this->output->writeln( "\nPage has attachments. Adding them...\n" );

				$attachments = $pageAttachmentsMap[$pageTitle];
				foreach ( $attachments as $attachment ) {
					$this->output->writeln( "Attachment: $attachment" );
					error_log( __LINE__ . " $attachment\n", 3, '/datadisk/workspace/migrate-confluence/iway/debug.log' );

					$drawIoFileHandler = new DrawIOFileHandler();

					// We do not need DrawIO data files in our wiki, just PNG image
					if ( $drawIoFileHandler->isDrawIODataFile( $attachment ) ) {
						continue;
					}

					if ( isset( $filesMap[$attachment] ) ) {
						$filePath = $filesMap[$attachment][0];
						error_log( __LINE__ . " $filePath\n", 3, '/datadisk/workspace/migrate-confluence/iway/debug.log' );
						$attachmentContent = file_get_contents( $filePath );

						if ( $drawIoFileHandler->isDrawIOImage( $attachment ) ) {
							// Find associated with DrawIO PNG image diagram XML
							// If image has "image1.drawio.png" name,
							// Then diagram XML will be stored in the "image1.drawio.unknown" file
							error_log( __LINE__ . " $attachment\n", 3, '/datadisk/workspace/migrate-confluence/iway/debug.log' );
							$diagramFileName = substr( $attachment, 0, -4 );
							error_log( __LINE__ . " $diagramFileName\n", 3, '/datadisk/workspace/migrate-confluence/iway/debug.log' );
							$diagramFileName = '.unknown';
							if ( isset( $filesMap[$diagramFileName] ) ) {
								$diagramContent = file_get_contents( $filesMap[$diagramFileName][0] );

								// Need to bake DrawIO diagram XML into the PNG image
								$attachmentContent = $drawIoFileHandler->bakeDiagramDataIntoImage(
									$attachmentContent, $diagramContent
								);
							} else {
								$this->output->writeln( "No DrawIO diagram XML was found for image '$attachment'" );
							}
						}

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

		$timeEnd = microtime( true );
		$timeExecution = round( ( $timeEnd - $timeStart )/60, 1 );
		$this->output->writeln( "\n\033[33mTime: $timeExecution\033[39m" );
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
