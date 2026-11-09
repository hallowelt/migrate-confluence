<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;

class UpdateAttachmentFileExtension extends ProcessorBase {

	public function execute(): void {
		$attachments = $this->workspaceDB->getAttachments();
		foreach ( $attachments as $attachment ) {
			$collection = $attachment['collection'];
			$fileExtension = $attachment['file_extension'] ?? '';
			if ( !empty( $fileExtension ) && $fileExtension !== 'unknown' ) {
				// File extension is already known, skip this attachment
				continue;
			}

			$collectionData = json_decode( $collection, true ) ?? [];
			$contentProperties = [];
			if ( !empty( $collectionData['contentProperties'] ) ) {
				$contentProperties = $collectionData['contentProperties'];

				foreach ( $contentProperties as $propertyId ) {
					$property = $this->workspaceDB->getContentPropertyById( (int)$propertyId );
					if ( $property === null ) {
						continue;
					}
					$data = [];
					if ( isset( $property['properties'] ) ) {
						$data = json_decode( $property['properties'], true );
					}

					$mediaType = '';
					if ( isset( $data['name'] ) && $data['name'] === 'MEDIA_TYPE' ) {
						$mediaType = $data['name'];
					} else {
						continue;
					}

					if ( !empty( $mediaType ) && $mediaType === 'MEDIA_TYPE' ) {
						// Update the attachment file extension based on the media type
						$fileExtension = $this->getFileExtensionFromMediaType( $data['stringValue'] );
						if ( empty( $fileExtension ) ) {
							$fileExtension = 'unknown';
						}
					}
				}
			}

			if ( $fileExtension === '' ) {
				$fileExtension = 'unknown';

				$filePath = $attachment['attachment_reference'] ?? '';
				if ( !empty( $filePath ) && file_exists( $filePath ) ) {
					$fileExtension = pathinfo( $filePath, PATHINFO_EXTENSION );
					if ( !empty( $fileExtension ) ) {
						$fileExtension = strtolower( $fileExtension );
					}
				}
			}

		// Update the attachment in the database with the new file extension
			$this->writer->updateAttachmentFileExtension(
				(int)$attachment['attachment_id'], strtolower( $fileExtension )
			);
		}
	}

	private function getFileExtensionFromMediaType( string $mediaType ): string {
		// File extension is encoded in media type like "image\/png"
		$fileExtension = '';
		if ( strpos( $mediaType, '/' ) !== false ) {
			$fileExtension = substr( $mediaType, strpos( $mediaType, '/' ) + 1 );
		}
		return $fileExtension ?: 'unknown';
	}
}
