<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use SplFileInfo;
use XMLReader;

class Attachments extends ProcessorBase {
	
	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param string $xmlPath
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB,
		private string $xmlPath
	) {}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$attachmentId = null;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$attachmentId = $this->getCDATAValue();
				} else {
					$attachmentId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( $attachmentId === null ) {
			return;
		}

		$confluenceFilename = '';
		if ( isset( $properties['fileName'] ) ) {
			$confluenceFilename = $properties['fileName'];
		}
		if ( $confluenceFilename === '' && isset( $properties['title'] ) ) {
			$confluenceFilename = $properties['title'];
		}

		$spaceId = -1;
		if ( isset( $properties['space'] ) ) {
			$spaceId = $properties['space'];
		}

		$containerContentId = -1;
		if ( isset( $properties['containerContent'] ) ) {
			$containerContentId = (int)$properties['containerContent'];
		}

		$contentStatus = '';
		if ( isset( $properties['contentStatus'] ) ) {
			$contentStatus = $properties['contentStatus'];
		}

		$attachmentReference = $this->makeAttachmentReference(
			$attachmentId,
			$containerContentId,
			$properties
		);

		$this->workspaceDB->addAttachment(
			$attachmentId,
			$spaceId,
			$confluenceFilename,
			$this->guessFileExtension( $attachmentReference, $confluenceFilename ),
			$containerContentId,
			$contentStatus,
			$attachmentReference,
			$properties
		);
	}

	/**
	 * @param int $attachmentId
	 * @param array $properties
	 * @return string
	 */
	private function makeAttachmentReference( int $attachmentId, int $containerContentId, array $properties ): string {
		$basePath = $this->xmlPath . '/attachments';

		$attachmentVersion = '';
		if ( isset( $properties['attachmentVersion'] ) ) {
			$attachmentVersion = $properties['attachmentVersion'];
		}
		if ( $attachmentVersion === '' ) {
			$attachmentVersion = $properties['version'];
		}
		if ( $attachmentVersion === '' ) {
			/**
			 * Sometimes there is no explicit version set in the "attachment" object. In such cases
			 * there we always fetch the highest number from the respective directory
			 */
			$attachmentVersion = '__LATEST__';
		}

		return $basePath . "/" . $containerContentId . '/' . $attachmentId . '/' . $attachmentVersion;
	}

	/**
	 * @param string $attachmentReference
	 * @return string
	 */
	private function guessFileExtension( string $attachmentReference, string $confluenceFilename ): string {
		$fileExtension = '';
		$file = new SplFileInfo( $attachmentReference );
		$fileExtension = $file->getExtension();

		if ( $fileExtension === '' ) {
			$filenameParts = explode( '.', $confluenceFilename );
			if ( count( $filenameParts ) > 1 ) {
				$fileExtension = end( $filenameParts );
			}
		}

		return $fileExtension;
	}
}
