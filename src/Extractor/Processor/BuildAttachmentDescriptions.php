<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

/**
 * Builds and stores file description wikitext for attachments whose wiki title
 * does not preserve the original filename. Runs after ExtractAttachmentsMetaData
 * so that category labellings are already available in attachments_meta.
 */
class BuildAttachmentDescriptions extends ProcessorBase {

	/**
	 * @return void
	 */
	public function execute(): void {
		$tables = [
			$this->workspaceDB->getPageAttachments(),
			$this->workspaceDB->getBlogPostAttachments(),
			$this->workspaceDB->getAdditionalAttachments(),
		];

		foreach ( $tables as $rows ) {
			foreach ( $rows as $row ) {
				$attachmentId = (int)$row['attachment_id'];
				$targetTitle = $row['target_attachment_filename'] ?? '';
				$originalFilename = $row['original_attachment_filename'] ?? '';
				$this->buildDescription( $attachmentId, $targetTitle, $originalFilename );
			}
		}
	}

	/**
	 * @param int $attachmentId
	 * @param string $targetTitle
	 * @param string $originalFilename
	 * @return void
	 */
	private function buildDescription( int $attachmentId, string $targetTitle, string $originalFilename ): void {
		if ( $originalFilename === '' ) {
			return;
		}

		$normalized = str_replace( [ ' ', '/' ], '_', $originalFilename );
		$normalized = preg_replace( '/_+/', '_', $normalized ) ?? $normalized;

		$colonPos = strpos( $targetTitle, ':' );
		$localTarget = $colonPos !== false ? substr( $targetTitle, $colonPos + 1 ) : $targetTitle;

		// Case-insensitive: ucfirst() in title building must not cause false positives.
		if ( stripos( $localTarget, $normalized ) !== false ) {
			return;
		}

		$quotedFileName = htmlspecialchars( $originalFilename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$description = "Original file name: <nowiki>$quotedFileName</nowiki>\n"
			. "{{DISPLAYTITLE:$quotedFileName|noerror}}";

		$metaData = $this->workspaceDB->getAttachmentMetaById( $attachmentId );
		foreach ( $metaData['categories'] ?? [] as $category ) {
			$description .= "\n[[Category:" . ucfirst( $category ) . "]]";
		}

		$this->workspaceDB->addAttachmentDescription( $attachmentId, $description );
	}
}
