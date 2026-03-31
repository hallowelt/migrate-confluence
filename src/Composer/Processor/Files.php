<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Files extends ProcessorBase {

	/** @var DataBuckets */
	private $customBuckets;

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'files';
	}

	/**
	 * @return void
	 */
	protected function writeOutputFile(): void {
		// As long as we have no import script for files embeded in xml
		// we do not write a files.xml
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		/**
		 * base64 hash of files may exceed php memory limit.
		 * Make sure enought memory is available or set
		 * ini_set( "memory_limit", "-1" );
		 */

		$this->customBuckets = new DataBuckets( [
			'title-uploads',
			'title-uploads-fail'
		] );

		$this->customBuckets->loadFromWorkspace( $this->workspace );

		$this->addDefaultFiles();
		$this->addAttachments();

		$this->customBuckets->saveToWorkspace( $this->workspace );
		$this->writeOutputFile();
	}

	private function addAttachments(): void {
		$titleRevisions = $this->buckets->getBucketData(
			'global-title-revisions'
		);

		$this->numOfXmlCountDigits = strlen( (string)count( $titleRevisions ) );

		/** Add grouped pages */
		foreach ( $titleRevisions as $pageTitle => $pageRevisions ) {
			if ( $this->skipTitle( $pageTitle ) ) {
				continue;
			}

			$sortedRevisions = $this->sortRevisions( $pageRevisions );
			foreach ( $sortedRevisions as $timestamp => $bodyContentIds ) {
				// Add attachments
				$this->addTitleAttachments( $pageTitle );
			}
		}
	}

	/**
	 * @return void
	 */
	private function addDefaultFiles(): void {
		$basepath = dirname( __DIR__ ) . '/_defaultfiles/';
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			$filename = basename( $file );
			$data = file_get_contents( $file );

			// XML containing files is supported by MediaWiki dumpBackup but can not be imported
			#$this->addFileRevision( $filename, '', $data );
			$this->workspace->saveUploadFile( $filename, $data );
		}
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
				/** Generalize file title. I can contain a namespace. */
				$filename = str_replace( ':', '_', $attachment );

				if ( isset( $filesMap[$filename] ) ) {
					$filePath = $filesMap[$filename][0];
					$attachmentContent = file_get_contents( $filePath );

					// XML containing files is supported by MediaWiki dumpBackup but can not be imported
					#$this->builder->addFileRevision( $attachment, '', $attachmentContent );
					$this->workspace->saveUploadFile( $filename, $attachmentContent );
					$this->customBuckets->addData( 'title-uploads', $pageTitle, $filename );
				} else {
					$this->output->writeln( "Attachment file was not found!" );
					$this->customBuckets->addData( 'title-uploads-fail', $pageTitle, $filename );
				}
			}
		}
	}
}
