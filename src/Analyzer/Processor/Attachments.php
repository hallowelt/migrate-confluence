<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use SplFileInfo;
use XMLReader;

class Attachments extends ProcessorBase {

	/** @var SplFileInfo */
	private $file;

	/**
	 * @param SplFileInfo $file
	 */
	public function __construct( SplFileInfo $file ) {
		$this->file = $file;
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'analyze-attachment-available-ids',
			'analyze-attachment-id-to-orig-filename-map',
			'analyze-attachment-id-to-space-id-map',
			'analyze-attachment-id-to-reference-map',
			'analyze-attachment-id-to-container-content-id-map',
			'analyze-attachment-id-to-content-status-map'
		];
	}

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

		$this->process( (int)$attachmentId, $properties );
	}

	/**
	 * @param int $attachmentId
	 * @param array $properties
	 * @return void
	 */
	private function process( int $attachmentId, array $properties ): void {
		$this->data['analyze-attachment-available-ids'][] = $attachmentId;

		$attachmentFilename = '';
		if ( isset( $properties['fileName'] ) ) {
			$attachmentFilename = $properties['fileName'];
		}
		if ( $attachmentFilename === '' && isset( $properties['title'] ) ) {
			$attachmentFilename = $properties['title'];
		}
		if ( $attachmentFilename !== '' ) {
			$this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId] = $attachmentFilename;
		}

		$attachmentSpaceId = null;
		if ( isset( $properties['space'] ) ) {
			$attachmentSpaceId = $properties['space'];
		}
		if ( is_int( $attachmentSpaceId ) ) {
			$this->data['analyze-attachment-id-to-space-id-map'][$attachmentId] = $attachmentSpaceId;
		}

		$attachmentReference = $this->makeAttachmentReference( $attachmentId, $properties );
		if ( $attachmentReference !== '' ) {
			$this->data['analyze-attachment-id-to-reference-map'][$attachmentId] = $attachmentReference;
		}

		$containerContentId = -1;
		if ( isset( $properties['containerContent'] ) ) {
			$containerContentId = (int)$properties['containerContent'];
		}
		if ( $containerContentId >= 0 ) {
			$this->data['analyze-attachment-id-to-container-content-id-map'][$attachmentId] = $containerContentId;
		}

		$attachmentNodeContentStatus = '';
		if ( isset( $properties['contentStatus'] ) ) {
			$attachmentNodeContentStatus = $properties['contentStatus'];
		}
		$this->data['analyze-attachment-id-to-content-status-map'][$attachmentId] = $attachmentNodeContentStatus;
	}

	/**
	 * @param int $attachmentId
	 * @param array $properties
	 * @return string
	 */
	private function makeAttachmentReference( int $attachmentId, array $properties ): string {
		$basePath = $this->file->getPath() . '/attachments';

		$containerId = '';
		if ( isset( $properties['content'] ) ) {
			$containerId = $properties['content'];
		}
		if ( $containerId === '' && isset( $properties['containerContent'] ) ) {
			$containerId = $properties['containerContent'];
		}

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

		$path = $basePath . "/" . $containerId . '/' . $attachmentId . '/' . $attachmentVersion;
		if ( !file_exists( $path ) ) {
			return '';
		}

		return $path;
	}
}
