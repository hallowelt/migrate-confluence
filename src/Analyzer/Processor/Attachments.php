<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalysisDataWriter;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use SplFileInfo;
use XMLReader;

class Attachments extends ProcessorBase {

	/**
	 * @param IAnalysisDataWriter $writer
	 * @param MigrationConfig $migrationConfig
	 * @param string $xmlPath
	 */
	public function __construct(
		private IAnalysisDataWriter $writer,
		private MigrationConfig $migrationConfig,
		private string $xmlPath
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$attachmentId = null;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$attachmentId = (int)$this->getCDATAValue();
				} else {
					$attachmentId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		if ( $attachmentId === null ) {
			return;
		}
		$referenceAttachmentId = $attachmentId;

		$confluenceFilename = '';
		if ( isset( $properties['fileName'] ) ) {
			$confluenceFilename = $properties['fileName'];
		}
		if ( $confluenceFilename === '' && isset( $properties['title'] ) ) {
			$confluenceFilename = $properties['title'];
		}

		$spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$spaceId = (int)$properties['space'];
		}

		$containerContentId = -1;
		if ( isset( $properties['containerContent'] ) ) {
			$containerContentId = (int)$properties['containerContent'];
		}

		$contentStatus = '';
		if ( isset( $properties['contentStatus'] ) ) {
			$contentStatus = $properties['contentStatus'];
		}

		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$originalVersionId = -1;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionId = (int)$properties['originalVersion'];
			$referenceAttachmentId = $originalVersionId;
		}

		if ( !$this->migrationConfig->getIncludeHistory() && $originalVersionId > 0 ) {
			return;
		}

		$historicalIds = [];
		if ( isset( $collection['historicalVersions'] ) ) {
			$historicalIds = $collection['historicalVersions'];
		}

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) && $properties['lastModificationDate'] !== '' ) {
			$lastModificationDate = $properties['lastModificationDate'];
		} elseif ( isset( $properties['creationDate'] ) && $properties['creationDate'] !== '' ) {
			$lastModificationDate = $properties['creationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );

		$lastModifier = '';
		if ( isset( $properties['lastModifier'] ) && $properties['lastModifier'] !== '' ) {
			$lastModifier = $properties['lastModifier'];
		} elseif ( isset( $properties['creator'] ) && $properties['creator'] !== '' ) {
			$lastModifier = $properties['creator'];
		}

		$attachmentReference = $this->makeAttachmentReference(
			$referenceAttachmentId,
			$containerContentId,
			$properties
		);

		$this->writer->addAttachment(
			$attachmentId,
			$spaceId,
			$confluenceFilename,
			$this->guessFileExtension( $attachmentReference, $confluenceFilename ),
			$containerContentId,
			$contentStatus,
			$version,
			$revisionTimestamp,
			$lastModifier,
			$originalVersionId,
			$attachmentReference,
			$historicalIds,
			$properties,
			$collection
		);
	}

	/**
	 * @param int $attachmentId
	 * @param int $containerContentId
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
