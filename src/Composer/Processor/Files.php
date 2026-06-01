<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Files extends FileProcessorBase {

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

	/**
	 * Add files related to pages
	 *
	 * @return void
	 */
	private function addPageAttachments(): void {
		$this->output->writeln( "\nAdding page attachments...\n" );

		$pageAttachments = $this->dataLookup->getPageAttachments();

		foreach ( $pageAttachments as $pageAttachment ) {
			$attachmentId = $pageAttachment['attachment_id'];

			$assocPageTitle = $this->dataLookup->getTargetPageTitleFromPageId(
				$pageAttachment['page_id']
			);
			if ( $this->skipTitle( $assocPageTitle ) ) {
				continue;
			}

			$attachmentPageTitle = $pageAttachment['target_attachment_filename'];
			$filename = $this->gereralizeFilename( $attachmentPageTitle );

			// We do not need DrawIO data files in our wiki, just PNG image
			if ( $this->isDrawioDataFile( $filename ) ) {
				continue;
			}

			$attachments = $this->dataLookup->getAttachmentRevisionsForAttachmentId( $attachmentId );
			foreach ( $attachments as $attachment ) {
				$attachmentId = $attachment['attachment_id'];
				if ( !isset( $attachment['attachment_reference'] ) ) {
					$this->output->writeln( "Attachment (ID: $attachmentId) file was not found!" );
					continue;
				}
				$timestamp = $attachment['revision_timestamp'];
				$version = $attachment['version'];
				$userKey = $attachment['last_modifier'];
				$username = $this->dataLookup->getUsernameFromUserKey( $userKey );
				$filePath = $attachment['attachment_reference'];

				if ( file_exists( $filePath ) ) {
					$this->output->writeln( "Attachment: $filename in version from $timestamp" );
				} else {
					$this->output->writeln( "Attachment $filename in version from $timestamp was not found!" );
					continue;
				}

				$testFilePath = $this->dest . '/images/' . $filename;
				if ( file_exists( $testFilePath ) ) {
					$this->output->writeln( "Attachment file override detected. Using override!" );
					$filePath = $testFilePath;
				} elseif ( file_exists( $filePath ) ) {
					$this->output->writeln( "Upload attachment file." );
				} else {
					$this->output->writeln( "Attachment file not found (ID: $attachmentId)!" );
					continue;
				}

				$attachmentContent = file_get_contents( $filePath );
				$subDir = "result/images/$filename/$version";
				$uploadFilePath = $this->workspace->saveUploadFile( $filename, $attachmentContent, $subDir );

				// XML containing files is supported by MediaWiki dumpBackup but can not be imported
				$this->builder->addFileRevision(
					$attachmentPageTitle,
					$this->getRelativeFilePath( $uploadFilePath ),
					$timestamp,
					$username
				);

				// Log file extension
				$this->deploymentInfo->addFileExtension( $attachment['file_extension'] );
			}
		}
	}

	/**
	 * Add additional files related to spaces but not to pages
	 *
	 * @return void
	 */
	private function addAdditionalAttachments(): void {
		$this->output->writeln( "\nAdding additional attachments...\n" );

		$additionalAttachments = $this->dataLookup->getAdditionalAttachments();

		foreach ( $additionalAttachments as $additionalAttachment ) {
			$attachmentId = $additionalAttachment['attachment_id'];

			$attachmentPageTitle = $additionalAttachment['target_attachment_filename'];
			$filename = $this->gereralizeFilename( $attachmentPageTitle );

			$attachments = $this->dataLookup->getAttachmentRevisionsForAttachmentId( $attachmentId );
			foreach ( $attachments as $attachment ) {
				$attachmentId = $attachment['attachment_id'];
				if ( !isset( $attachment['attachment_reference'] ) ) {
					$this->output->writeln( "Attachment (ID: $attachmentId) file was not found!" );
					continue;
				}
				$timestamp = $attachment['revision_timestamp'];
				$version = $attachment['version'];
				$userKey = $attachment['last_modifier'];
				$username = $this->dataLookup->getUsernameFromUserKey( $userKey );
				$filePath = $attachment['attachment_reference'];

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
						$filePath = $testFilePath;
					} elseif ( file_exists( $filePath ) ) {
						$this->output->writeln( "Upload attachment file." );
					} else {
						$this->output->writeln( "Attachment file not found (ID: $attachmentId)!" );
						continue;
					}

					$attachmentContent = file_get_contents( $filePath );
					$subDir = "result/images/$filename/$version";
					$uploadFilePath = $this->workspace->saveUploadFile( $filename, $attachmentContent, $subDir );

					$timestamp = $attachment['revision_timestamp'];
					$userKey = $attachment['last_modifier'];
					$username = $this->dataLookup->getUsernameFromUserKey( $userKey );

					// XML containing files is supported by MediaWiki dumpBackup but can not be imported
					$this->builder->addFileRevision(
						$attachmentPageTitle,
						$this->getRelativeFilePath( $uploadFilePath ),
						$timestamp,
						$username
					);

					// Log file extension
					$this->deploymentInfo->addFileExtension( $attachment['file_extension'] );
				} else {
					$this->output->writeln( "Attachment file was not found!" );
				}
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
			$attachmentPageTitle = "File:$filename";
			$data = file_get_contents( $file );

			$uploadFilePath = $this->workspace->saveUploadFile( $filename, $data );

			// XML containing files is supported by MediaWiki dumpBackup but can not be imported
			$this->builder->addFileRevision(
				$attachmentPageTitle,
				$this->getRelativeFilePath( $uploadFilePath ),
				'',
				''
			);
		}
	}
}
