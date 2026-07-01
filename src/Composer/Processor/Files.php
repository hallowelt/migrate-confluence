<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class Files extends FileProcessorBase {

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param ComposerSkipHelper $skipHelper
	 */
	public function __construct(
		protected DBComposerDataLookup $dataLookup,
		protected Workspace $workspace,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
		protected ComposerDeploymentInfo $deploymentInfo,
		protected ComposerSkipHelper $skipHelper
	) {
		parent::__construct( $dataLookup, $workspace, $output, $dest, $migrationConfig );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
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

		$pageAttachments = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				$pageAttachments = array_merge(
					$pageAttachments,
					$this->dataLookup->getPageAttachments( (int)$spaceId )
				);
			}
		} else {
			$pageAttachments = $this->dataLookup->getPageAttachments();
		}

		foreach ( $pageAttachments as $pageAttachment ) {
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

		$blogPostAttachments = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				$blogPostAttachments = array_merge(
					$blogPostAttachments,
					$this->dataLookup->getBlogPostAttachments( (int)$spaceId )
				);
			}
		} else {
			$blogPostAttachments = $this->dataLookup->getBlogPostAttachments();
		}

		foreach ( $blogPostAttachments as $blogPostAttachment ) {
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

		$filename = $this->generalizeFilename( $attachmentPageTitle );

		// We do not need DrawIO data files in our wiki, just PNG image
		if ( $this->isDrawioDataFile( $filename ) ) {
			return;
		}

		$uploadPath = $this->getUploadPath();
		$originalFilename = $attachmentRecord['original_attachment_filename'] ?? '';
		$comment = $this->buildShorteningComment( $attachmentPageTitle, $originalFilename );

		$attachments = $this->dataLookup->getAttachmentRevisionsForAttachmentId( $attachmentId );
		foreach ( $attachments as $attachment ) {
			if ( isset( $attachment['attachment_reference'] ) ) {
				$timestamp = $attachment['revision_timestamp'];
				/* the file's author is in $attachment['last_modifier'] . To use this information,
				 * though, we need to make sure somehow that the user exists in the wiki,
				 * otherwise the file import will crash.
				 */
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
					"$timestamp-$filename", $attachmentContent, $uploadPath
				);

				// XML containing files is supported by MediaWiki dumpBackup but can not be imported
				$this->builder->addFileRevision(
					$attachmentPageTitle,
					$this->getRelativeFilePath( $uploadFilePath ),
					$timestamp,
					'',
					$comment
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

		$additionalAttachments = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				$additionalAttachments = array_merge(
					$additionalAttachments,
					$this->dataLookup->getAdditionalAttachments( (int)$spaceId )
				);
			}
		} else {
			$additionalAttachments = $this->dataLookup->getAdditionalAttachments();
		}

		$uploadPath = $this->getUploadPath();

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

			$filename = $this->generalizeFilename( $attachmentPageTitle );

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

					// Check for temporary files created by converter (e.g. a drawio file)
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
						$filename, $attachmentContent, $uploadPath
					);

					$timestamp = $attachment['revision_timestamp'];
					/* same as above: to use the author info in $attachment['last_modifier'],
					 * we need target wiki user info (or be sure that we import the user ourselfs).
					 */

					$originalFilename = $additionalAttachment['original_attachment_filename'] ?? '';
					$comment = $this->buildShorteningComment( $attachmentPageTitle, $originalFilename );

					// XML containing files is supported by MediaWiki dumpBackup but can not be imported
					$this->builder->addFileRevision(
						$attachmentPageTitle,
						$this->getRelativeFilePath( $uploadFilePath ),
						$timestamp,
						'',
						$comment
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
	 * Return a comment noting the original file name when we altered it (e.g. through abbreviation)
	 */
	private function buildShorteningComment( string $targetTitle, string $originalFilename ): string {
		if ( $originalFilename === '' ) {
			return '';
		}
		$normalized = str_replace( [ ' ', '/' ], '_', $originalFilename );
		$normalized = preg_replace( '/_+/', '_', $normalized ) ?? $normalized;

		// Strip namespace prefix so we only search the local title part.
		$colonPos = strpos( $targetTitle, ':' );
		$localTarget = $colonPos !== false ? substr( $targetTitle, $colonPos + 1 ) : $targetTitle;

		// Case-insensitive: WindowsFilename applies ucfirst() which must not cause false positives.
		if ( stripos( $localTarget, $normalized ) !== false ) {
			return '';
		}
		$quotedFileName = htmlspecialchars( $originalFilename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		return "Original file name: <nowiki>$quotedFileName</nowiki>\n{{DISPLAYTITLE:$quotedFileName|noerror}}";
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
