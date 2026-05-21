<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Files extends ProcessorBase {

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

		$this->addDefaultFiles();
		$this->addPageAttachments();
		$this->addAdditionalAttachments();

		$this->writeOutputFile();
	}

	private function addPageAttachments(): void {
		$this->output->writeln( "\nAdding page attachments...\n" );

		$pageAttachments = $this->dataLookup->getPageAttachments();

		foreach ( $pageAttachments as $pageAttachment ) {
			$attachmentId = $pageAttachment['attachment_id'];
			$pageTitle = $pageAttachment['target_attachment_filename'];

			$attachment = $this->dataLookup->getAttachment( $attachmentId );

			/** Generalize file title. I can contain a namespace. */
			$filename = str_replace( ':', '_', $pageTitle );

			$drawIoFileHandler = new DrawIOFileHandler();

			// We do not need DrawIO data files in our wiki, just PNG image
			if ( $drawIoFileHandler->isDrawIODataFile( $filename ) ) {
				continue;
			}

			if ( isset( $attachment['attachment_reference'] ) ) {
				$filePath = $attachment['attachment_reference'];

				if ( file_exists( $filePath ) ) {
					$this->output->writeln( "Attachment: $filename" );
				} else {
					$this->output->writeln( "Attachment file was not found!" );
					continue;
				}
				$attachmentContent = file_get_contents( $filePath );

				// XML containing files is supported by MediaWiki dumpBackup but can not be imported
				#$this->builder->addFileRevision( $attachment, '', $attachmentContent );
				$this->workspace->saveUploadFile( $filename, $attachmentContent );

				$this->deploymentInfo->addFileExtension( $attachment['file_extension'] );
			} else {
				$this->output->writeln( "Attachment file was not found!" );
			}
		}
	}

	private function addAdditionalAttachments(): void {
		$this->output->writeln( "\nAdding additional attachments...\n" );

		$additionalAttachments = $this->dataLookup->getAdditionalAttachments();

		foreach ( $additionalAttachments as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$pageTitle = $attachment['target_attachment_filename'];

			$attachment = $this->dataLookup->getAttachment( $attachmentId );

			/** Generalize file title. I can contain a namespace. */
			$filename = str_replace( ':', '_', $pageTitle );

			$drawIoFileHandler = new DrawIOFileHandler();

			// We do not need DrawIO data files in our wiki, just PNG image
			if ( $drawIoFileHandler->isDrawIODataFile( $filename ) ) {
				continue;
			}

			if ( isset( $attachment['attachment_reference'] ) ) {
				$filePath = $attachment['attachment_reference'];

				if ( file_exists( $filePath ) ) {
					$this->output->writeln( "Attachment: $filename" );
				} else {
					$this->output->writeln( "Attachment file was not found!" );
					continue;
				}

				$testFilePath = $this->dest . '/images/' . $filename;

				if ( file_exists( $testFilePath ) ) {
					$this->output->writeln( "Attachment file override detected. Using override!" );
					$attachmentContent = file_get_contents( $testFilePath );
					continue;
				} else {
					$attachmentContent = file_get_contents( $filePath );
				}

				// XML containing files is supported by MediaWiki dumpBackup but can not be imported
				#$this->builder->addFileRevision( $attachment, '', $attachmentContent );
				$this->workspace->saveUploadFile( $filename, $attachmentContent );
			} else {
				$this->output->writeln( "Attachment file was not found!" );
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
}
