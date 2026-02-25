<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class AttachmentFallback extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/** @var mixed */
	private $attachmentId;

	/** @var string */
	private $attachmentOrigFilename = '';

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [
			'analyze-attachment-available-ids',
			'analyze-added-attachment-id',
			'analyze-attachment-id-to-orig-filename-map',
			'analyze-attachment-id-to-space-id-map',
			'analyze-attachment-id-to-reference-map',
			'global-page-id-to-title-map',
			'analyze-page-id-to-confluence-key-map',
			'global-space-id-to-prefix-map',
			'analyze-add-file',
			'global-attachment-orig-filename-target-filename-map',
			'debug-analyze-invalid-titles-attachment-id-to-title',
			'global-filenames-to-filetitles-map',
			'analyze-attachment-id-to-target-filename-map',
			'global-title-attachments'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'debug-analyze-invalid-titles-attachment-id-to-title',
			'global-additional-files',
			'analyze-add-file',
			'global-attachment-orig-filename-target-filename-map',
			'global-filenames-to-filetitles-map',
			'analyze-attachment-id-to-target-filename-map',
			'global-title-attachments'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Attachment' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$attachmentNodeContentStatus = $this->xmlHelper->getPropertyValue( 'contentStatus', $objectNode );
		if ( strtolower( $attachmentNodeContentStatus ) !== 'current' ) {
			return;
		}
		$this->attachmentId = $this->xmlHelper->getIDNodeValue( $objectNode );
		if ( in_array( $this->attachmentId, $this->data['analyze-added-attachment-id'] ) ) {
			return;
		}
		if ( !in_array( $this->attachmentId, $this->data['analyze-attachment-available-ids'] ) ) {
			return;
		}
		if ( !isset( $this->data['analyze-attachment-id-to-orig-filename-map'][$this->attachmentId] ) ) {
			return;
		}
		$this->attachmentOrigFilename = $this->data['analyze-attachment-id-to-orig-filename-map'][$this->attachmentId];

		$this->process( $objectNode );
	}

	private function process( DOMElement $node ): void {
		// Check to which page attachment belongs
		$targetTitle = '';
		$confluenceKey = '';
		$containerContentId = $this->xmlHelper->getPropertyValue( 'containerContent', $node );
		if ( $containerContentId !== null ) {
			if ( isset( $data['global-page-id-to-title-map'][$containerContentId] ) ) {
				$targetTitle = $this->data['global-page-id-to-title-map'][$containerContentId];
			}
			if ( isset( $data['analyze-page-id-to-confluence-key-map'][$containerContentId] ) ) {
				$confluenceKey = $this->data['analyze-page-id-to-confluence-key-map'][$containerContentId];
			} else {
				return;
			}
		}
		// TODO: Is this wise?
		$attachmentSpaceId = 0;
		if ( isset( $data['analyze-attachment-id-to-space-id-map'][$this->attachmentId] ) ) {
			$attachmentSpaceId = $this->data['analyze-attachment-id-to-space-id-map'][$this->attachmentId];
		}
		$attachmentTargetFilename = $this->makeAttachmentTargetFilenameFromData(
			$confluenceKey, $this->attachmentId, $attachmentSpaceId, $this->attachmentOrigFilename,
			$targetTitle, $this->data['global-space-id-to-prefix-map']
		);
		if ( $attachmentTargetFilename === '' ) {
			$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$this->attachmentId]
				= $attachmentTargetFilename;
			return;
		}

		if ( !isset( $this->data['analyze-attachment-id-to-reference-map'][$this->attachmentId] ) ) {
			$this->output->writeln(
				//phpcs:ignore Generic.Files.LineLength.TooLong
				"\033[31m\t- File '$this->attachmentId' ($attachmentTargetFilename) not found\033[39m"
			);
			return;
		}

		$attachmentReference = $this->data['analyze-attachment-id-to-reference-map'][$this->attachmentId];

		if ( $confluenceKey !== '' ) {
			$this->data['global-title-attachments'][$targetTitle][] = $attachmentTargetFilename;
			$this->output->writeln( "Add attachment $attachmentTargetFilename (fallback: {$confluenceKey})" );
		} else {
			$this->data['global-additional-files'][$attachmentTargetFilename] = $attachmentReference;
			$this->output->writeln( "Add attachment $attachmentTargetFilename (additional)" );
		}

		$this->data['analyze-add-file'][$attachmentTargetFilename] = $attachmentReference;
		$this->data['analyze-added-attachment-id'][] = $this->attachmentId;

		$confluenceFileKey = str_replace( ' ', '', "{$confluenceKey}---{$this->attachmentOrigFilename}" );

		$this->data['global-filenames-to-filetitles-map'][$confluenceFileKey]
			= $attachmentTargetFilename;
		$this->data['analyze-attachment-id-to-target-filename-map'][$this->attachmentId]
			= $attachmentTargetFilename;
		$this->data['global-attachment-orig-filename-target-filename-map'][$this->attachmentOrigFilename]
			= $attachmentTargetFilename;
	}

	/**
	 * @param string $pageConfluenceTitle
	 * @param int $attachmentId
	 * @param int $attachmentSpaceId
	 * @param string $attachmentOrigFilename
	 * @param string $containerTitle
	 * @param array $spaceIdToPrefixMap
	 * @return string
	 */
	private function makeAttachmentTargetFilenameFromData(
		string $pageConfluenceTitle, int $attachmentId, int $attachmentSpaceId,
		string $attachmentOrigFilename, string $containerTitle, array $spaceIdToPrefixMap
	): string {
		$filenameBuilder = new FilenameBuilder( $spaceIdToPrefixMap, null );
		try {
			$targetName = $filenameBuilder->buildFromAttachmentData(
				$attachmentSpaceId, $attachmentOrigFilename, $containerTitle );
		} catch ( InvalidTitleException $e ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.
				$shortTargetTitle = basename( $containerTitle );
				$targetName = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId, $attachmentOrigFilename, $shortTargetTitle );
			} catch ( InvalidTitleException $ex ) {
				$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$attachmentId]
					= $ex->getInvalidTitle();
				$this->logger->error( $ex->getMessage() );
				$targetName = $ex->getInvalidTitle();
			}
		}

		/*
		 * Some attachments do not have a file extension available. We try
		 * to find an extension by looking a the content type, but
		 * sometimes even this won't help... ("octet-stream")
		 */
		$file = new SplFileInfo( $targetName );
		if ( $this->hasNoExplicitFileExtension( $file ) ) {
			$this->logger->debug(
				"Could not find file extension for $attachmentId"
			);
			$targetName .= '.unknown';
		}

		$fileKey = "{$pageConfluenceTitle}---$attachmentOrigFilename";
		// Some normalization
		$fileKey = str_replace( ' ', '_', $fileKey );
		$this->data['global-filenames-to-filetitles-map'][$fileKey] = $targetName;

		return $targetName;
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	private function hasNoExplicitFileExtension( $file ) {
		if ( $file->getExtension() === '' ) {
			return true;
		}
		// Evil hack for Names like "02.1 Some-Workflow File"
		if ( strlen( $file->getExtension() ) > 10 ) {

		}
		return false;
	}
}
