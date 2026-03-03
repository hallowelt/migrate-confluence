<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class Attachments extends ProcessorBase {

	/** @var SplFileInfo */
	private $file;

	/** @var bool */
	private $includeHistory;

	/** @var XMLHelper */
	protected $xmlHelper;

	/**
	 * @param SplFileInfo $file
	 * @param boolean $includeHistory
	 */
	public function __construct( SplFileInfo $file, bool $includeHistory ) {
		$this->file = $file;
		$this->includeHistory = $includeHistory;
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
			'analyze-attachment-id-to-page-id-map',
			'analyze-attachment-id-to-content-status-map',
			'global-attachment-revisions'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Attachment' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$this->process( $objectNode );
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function process( DOMElement $node ): void {
		$attachmentId = $this->xmlHelper->getIDNodeValue( $node );
		if ( $attachmentId < 0  ) {
			return;
		}

		$attachmentNodeContentStatus = $this->xmlHelper->getPropertyValue( 'contentStatus', $node );
		if ( strtolower( $attachmentNodeContentStatus ) !== 'current' ) {
			return;
		}
		$this->data['analyze-attachment-id-to-content-status-map'][$attachmentId] = $attachmentNodeContentStatus;

		// Process only latest version of attachments as long as history is not required by config
		$originalVersionId = $this->xmlHelper->getPropertyValue( 'originalVersion', $node );
		if ( !$originalVersionId ) {
			$originalVersionId = $this->xmlHelper->getPropertyValue( 'originalVersionId', $node );
			$originalVersionId = ( (int)$originalVersionId > 0 )? (int)$originalVersionId: null;
		}
		// All attachments have tag originalVersionid but in latest version it is empty
		if ( !$this->includeHistory && ( $originalVersionId !== null ) ) {
			return;
		}
		if ( $originalVersionId === null ) {
			$originalVersionId = $attachmentId;
		}

		$this->data['analyze-attachment-available-ids'][] = $attachmentId;

		$attachmentFilename = $this->xmlHelper->getPropertyValue( 'fileName', $node );
		if ( $attachmentFilename === null ) {
			$attachmentFilename = $this->xmlHelper->getPropertyValue( 'title', $node );
		}

		if ( $attachmentFilename !== '' && is_int( $attachmentId ) ) {
			$this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId] = $attachmentFilename;
		}
		$attachmentSpaceId = $this->xmlHelper->getPropertyValue( 'space', $node );
		if ( $attachmentSpaceId !== null ) {
			$this->data['analyze-attachment-id-to-space-id-map'][$attachmentId] = (int)$attachmentSpaceId;
		}

		$attachmentVersion = $this->xmlHelper->getPropertyValue( 'attachmentVersion', $node );
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = $this->xmlHelper->getPropertyValue( 'version', $node );
		}
		/**
		 * Sometimes there is no explicit version set in the "attachment" object. In such cases
		 * there we always fetch the highest number from the respective directory
		 */
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = '__LATEST__';
		}

		$lastModificationDate = $this->xmlHelper->getPropertyValue( 'creationDate', $node );
		$time = strtotime( $lastModificationDate );
		$attachmentTimestamp = date( 'YmdHis', $time );

		$this->data['global-attachment-revisions'][$originalVersionId][] =
			$attachmentId . '@' . $attachmentVersion . '-' . $attachmentTimestamp;

		$attachmentReference = $this->makeAttachmentReference( $this->xmlHelper, $node, $attachmentVersion );
		if ( $attachmentReference !== '' ) {
			$this->data['analyze-attachment-id-to-reference-map'][$attachmentId] = $attachmentReference;
		}

		$containerContent = $this->xmlHelper->getPropertyNode( 'containerContent', $node );
		if ( $containerContent instanceof DOMElement ) {
			$containerContentId = $this->xmlHelper->getIDNodeValue( $containerContent );
			if ( $containerContentId >= 0 ) {
				$this->data['analyze-attachment-id-to-page-id-map'][$attachmentId] = $containerContentId;
			}
		}
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $attachment
	 * @return string
	 */
	private function makeAttachmentReference(
		XMLHelper $xmlHelper, DOMElement $attachment, $attachmentVersion
	): string {
		$basePath = $this->file->getPath() . '/attachments';
		$attachmentId = $xmlHelper->getIDNodeValue( $attachment );
		$containerId = $xmlHelper->getPropertyValue( 'content', $attachment );
		if ( empty( $containerId ) ) {
			$containerId = $xmlHelper->getPropertyValue( 'containerContent', $attachment );
		}

		$path = $basePath . "/" . $containerId . '/' . $attachmentId . '/' . $attachmentVersion;
		if ( !file_exists( $path ) ) {
			return '';
		}

		return $path;
	}
}
