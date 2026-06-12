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
		$this->addBlogPostAttachments();
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

		foreach ( $this->dataLookup->getPageAttachments() as $pageAttachment ) {
			$assocPageTitle = $this->dataLookup->getWikiPageTitleFromPageId(
				$pageAttachment['page_id']
			);
			$this->processAttachment( $pageAttachment, $assocPageTitle );
		}
	}

	/**
	 * Add files related to blog posts
	 *
	 * @return void
	 */
	private function addBlogPostAttachments(): void {
		$this->output->writeln( "\nAdding blog post attachments...\n" );

		foreach ( $this->dataLookup->getBlogPostAttachments() as $blogPostAttachment ) {
			$assocPageTitle = $this->dataLookup->getWikiBlogPostTitleFromBlogPostId(
				$blogPostAttachment['blog_post_id']
			);
			$this->processAttachment( $blogPostAttachment, $assocPageTitle );
		}
	}

	/**
	 * Process a single attachment entry (from either page_attachments or blog_post_attachments)
	 * and write all its file revisions into the output XML.
	 *
	 * @param array $attachmentRecord Row from page_attachments or blog_post_attachments,
	 *   must contain 'attachment_id' and 'target_attachment_filename'.
	 * @param string|null $assocPageTitle Wiki title of the owning page or blog post.
	 * @return void
	 */
	private function processAttachment( array $attachmentRecord, ?string $assocPageTitle ): void {
		$attachmentId = $attachmentRecord['attachment_id'];
		$attachmentPageTitle = $attachmentRecord['target_attachment_filename'];

		if ( $this->skipHelper->skipWikiTitle( $assocPageTitle ) ) {
			$this->output->writeln( "Skip attachments for page title $assocPageTitle." );
			return;
		}
		$this->output->writeln( "Processing attachments for page title $assocPageTitle ..." );

		if ( $this->skipAttachmentId( $attachmentId, $attachmentPageTitle ) ) {
			$this->deploymentInfo->addSkippedPage( $attachmentPageTitle );
			return;
		}

		$filename = $this->gereralizeFilename( $attachmentPageTitle );

		// We do not need DrawIO data files in our wiki, just PNG image
		if ( $this->isDrawioDataFile( $filename ) ) {
			return;
		}

		$attachments = $this->dataLookup->getAttachmentRevisionsForAttachmentId( $attachmentId );
		foreach ( $attachments as $attachment ) {
			if ( isset( $attachment['attachment_reference'] ) ) {
				$timestamp = $attachment['revision_timestamp'];
				$userKey = $attachment['last_modifier'];
				// File import crashes if the user is unknown
				#$username = $this->dataLookup->getUsernameFromUserKey( $userKey ) ?? $userKey;
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
				$uploadFilePath = $this->workspace->saveUploadFile(
					"$timestamp-$filename", $attachmentContent, "result/images/$filename"
				);

				// XML containing files is supported by MediaWiki dumpBackup but can not be imported
				$this->builder->addFileRevision(
					$attachmentPageTitle,
					$this->getRelativeFilePath( $uploadFilePath ),
					$timestamp,
					''
				);

				// Log file extension
				$this->deploymentInfo->addFileExtension( $attachment['file_extension'] );
			} else {
				$this->output->writeln( "Attachment file was not found!" );
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

			if ( $this->skipHelper->skipWikiTitle( $attachmentPageTitle ) ) {
				$this->output->writeln( "Skip additional attachment $attachmentPageTitle." );
				continue;
			}

			if ( $this->skipAttachmentId( $attachmentId, $attachmentPageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $attachmentPageTitle );
				continue;
			}

			$filename = $this->gereralizeFilename( $attachmentPageTitle );

			$attachments = $this->dataLookup->getAttachmentRevisionsForAttachmentId( $attachmentId );
			foreach ( $attachments as $attachment ) {

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
					$uploadFilePath = $this->workspace->saveUploadFile( $filename, $attachmentContent );

					$timestamp = $attachment['revision_timestamp'];
					$userKey = $attachment['last_modifier'];
					// File import crashes if the user is unknown
					$username = $this->dataLookup->getUsernameFromUserKey( $userKey ) ?? $userKey;

					// XML containing files is supported by MediaWiki dumpBackup but can not be imported
					$this->builder->addFileRevision(
						$attachmentPageTitle,
						$this->getRelativeFilePath( $uploadFilePath ),
						$timestamp,
						''
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
			$attachmentPageTitle = $filename;
			$data = file_get_contents( $file );

			$uploadFilePath = $this->workspace->saveUploadFile( $filename, $data, "result/images/$filename" );

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
