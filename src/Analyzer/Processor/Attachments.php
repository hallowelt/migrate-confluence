<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class Attachments extends ProcessorBase {

	/** @var SplFileInfo */
	private $file;

	/** @var XMLHelper */
	protected $xmlHelper;

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
		if ( $attachmentId < 0 ) {
			return;
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
		if ( is_int( $attachmentId ) ) {
			$this->data['analyze-attachment-id-to-space-id-map'][$attachmentId] = $attachmentSpaceId;
		}
		$attachmentReference = $this->makeAttachmentReference( $this->xmlHelper, $node );
		if ( $attachmentReference !== '' ) {
			$this->data['analyze-attachment-id-to-reference-map'][$attachmentId] = $attachmentReference;
		}
		$containerContent = $this->xmlHelper->getPropertyNode( 'containerContent', $node );
		if ( $containerContent instanceof DOMElement ) {
			$containerContentId = $this->xmlHelper->getIDNodeValue( $containerContent );
			if ( $containerContentId >= 0 ) {
				$this->data['analyze-attachment-id-to-container-content-id-map'][$attachmentId] = $containerContentId;
			}
		}
		$attachmentNodeContentStatus = $this->xmlHelper->getPropertyValue( 'contentStatus', $node );
		$this->data['analyze-attachment-id-to-content-status-map'][$attachmentId] = $attachmentNodeContentStatus;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $attachment
	 * @return void
	 */
	private function makeAttachmentReference( XMLHelper $xmlHelper, DOMElement $attachment ) {
		$basePath = $this->file->getPath() . '/attachments';
		$attachmentId = $xmlHelper->getIDNodeValue( $attachment );
		$containerId = $xmlHelper->getPropertyValue( 'content', $attachment );
		if ( empty( $containerId ) ) {
			$containerId = $xmlHelper->getPropertyValue( 'containerContent', $attachment );
		}
		$attachmentVersion = $xmlHelper->getPropertyValue( 'attachmentVersion', $attachment );
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = $xmlHelper->getPropertyValue( 'version', $attachment );
		}

		/**
		 * Sometimes there is no explicit version set in the "attachment" object. In such cases
		 * there we always fetch the highest number from the respective directory
		 */
		if ( empty( $attachmentVersion ) ) {
			$attachmentVersion = '__LATEST__';
		}

		$path = $basePath . "/" . $containerId . '/' . $attachmentId . '/' . $attachmentVersion;
		if ( !file_exists( $path ) ) {
			return '';
		}

		return $path;
	}
}
