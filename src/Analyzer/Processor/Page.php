<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;
use SplFileInfo;

class Page extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/** @var array */
	private $includeSpaceKey = [];

	/** @var string */
	private $mainpage = 'Main Page';

	/** @var bool */
	private $includeHistory = false;

	/** @var mixed */
	private $spaceId;

	/** @var mixed */
	private $pageId;

	/** @var string */
	private $targetTitle = '';

	/**
	 * @param array $includeSpaceKey
	 * @param string $mainpage
	 * @param bool $includeHistory
	 */
	public function __construct(
		array $includeSpaceKey,
		string $mainpage,
		bool $includeHistory
	) {
		$this->includeSpaceKey = $includeSpaceKey;
		$this->mainpage = $mainpage;
		$this->includeHistory = $includeHistory;
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [
			'global-space-id-to-prefix-map',
			'global-space-id-to-key-map',
			'global-space-id-homepages',
			'analyze-page-id-to-parent-page-id-map',
			'analyze-attachment-id-to-orig-filename-map',
			'analyze-attachment-id-to-space-id-map',
			'analyze-attachment-id-to-reference-map',
			'analyze-body-content-id-to-page-id-map',
			'analyze-pages-titles-map',
			'analyze-page-id-to-confluence-key-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'debug-analyze-invalid-titles-page-id-to-title',
			'debug-analyze-invalid-titles-attachment-id-to-title',
			'analyze-page-id-to-confluence-key-map',
			'analyze-pages-titles-map',
			'analyze-page-id-to-title-map',
			'analyze-title-revisions',
			'analyze-title-to-attachment-title',
			'analyze-attachment-id-to-target-filename-map',
			'analyze-add-file',
			'analyze-added-attachment-id',
			'global-page-id-to-space-id',
			'global-body-content-id-to-page-id-map',
			'global-filenames-to-filetitles-map',
			'global-attachment-orig-filename-target-filename-map',
			'global-filenames-to-filetitles-map',
			'global-title-attachments',
			'analyze-page-id-to-confluence-title-map',
		];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Page' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}
		$status = $this->xmlHelper->getPropertyValue( 'contentStatus', $objectNode );
		if ( !$this->includeHistory && ( $status !== 'current' ) ) {
			return;
		}
		$this->spaceId = $this->xmlHelper->getPropertyValue( 'space', $objectNode );
		if ( $this->spaceId === null ) {
			return;
		}
		if ( !isset( $this->data['global-space-id-to-key-map'][$this->spaceId] ) ) {
			return;
		}
		$spaceKey = $this->data['global-space-id-to-key-map'][$this->spaceId];

		if (
			!empty( $this->includeSpaceKey )
			&& !in_array( strtolower( $spaceKey ), $this->includeSpaceKey )
		) {
			return;
		}
		$originalVersionID = $this->xmlHelper->getPropertyValue( 'originalVersion', $objectNode );
		if ( $originalVersionID !== null ) {
			return;
		}

		$this->pageId = $this->xmlHelper->getIDNodeValue( $objectNode );

		$titleBuilder = new TitleBuilder(
			$this->data['global-space-id-to-prefix-map'], $this->data['global-space-id-homepages'],
			$this->data['analyze-page-id-to-parent-page-id-map'],
			$this->data['analyze-page-id-to-confluence-title-map'], $this->xmlHelper, $this->mainpage
		);
		try {
			$this->targetTitle = $titleBuilder->buildTitle( $objectNode );
		} catch ( InvalidTitleException $ex ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => $ex->getInvalidTitle()
			];
			// We don't want to loose this page. Title can be modified after analyze process
			$this->targetTitle = $ex->getInvalidTitle();
		}

		if ( $this->targetTitle === '' ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [ $this->pageId => $this->targetTitle ];
			return;
		}

		$this->output->writeln( "Add page '$this->targetTitle' (ID:$this->pageId)" );

		$this->process( $objectNode );
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function process( DOMElement $node ): void {
		/**
		 * Adds data bucket "analyze-pages-titles-map", which contains mapping from page title itself to
		 * full page title.
		 * Full page title contains parent pages and namespace (if it is not general space).
		 *
		 * After testing for title validity and sanitizing titles they will be added to global-pages-titles-map later.
		 * Example:
		 * "Detailed_planning" -> "Dokumentation/Detailed_planning"
		 */
		$pageConfluenceTitle = $this->xmlHelper->getPropertyValue( 'title', $node );
		$genericTitleBuilder = new GenericTitleBuilder( [] );
		$pageConfluenceTitle = $genericTitleBuilder
			->appendTitleSegment( $pageConfluenceTitle )->build();
		// We need to preserve the spaceID, so we can properly resolve cross-space links
		// in the `convert` stage
		$pageConfluenceTitle = "$this->spaceId---{$pageConfluenceTitle}";
		// Some normalization
		$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
		$this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] = $pageConfluenceTitle;
		$this->data['analyze-pages-titles-map'][$pageConfluenceTitle] = $this->targetTitle;
		// Also add pages IDs in Confluence to full page title mapping.
		// It is needed to have enough context on converting stage,
		// to know from filename which page is currently being converted.

		$this->data['analyze-page-id-to-title-map'][$this->pageId] = $this->targetTitle;
		$this->data['global-page-id-to-space-id'][$this->pageId] = $this->spaceId;

		$revisionTimestamp = $this->buildRevisionTimestamp( $this->xmlHelper, $node );
		$bodyContentIds = $this->getBodyContentIds( $this->xmlHelper, $node );
		if ( !empty( $bodyContentIds ) ) {
			foreach ( $bodyContentIds as $bodyContentId ) {
				// TODO: Add UserImpl-key or directly MediaWiki username
				// (could also be done in `extract` as "metadata" )
				$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
			}
		} else {
			$bodyContentIds = [];
			foreach ( $this->data['analyze-body-content-id-to-page-id-map'] as $bodyContentId => $contentPageId ) {
				if ( $this->pageId === $contentPageId ) {
					$bodyContentIds[] = $bodyContentId;

					$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
				}
			}
		}

		$version = $this->xmlHelper->getPropertyValue( 'version', $node );
		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";

		$this->data['analyze-title-revisions'][$this->targetTitle][] = $revision;

		// Find attachments
		$this->getAttachmentsFromCollection( $this->xmlHelper, $node, $this->spaceId );
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return string
	 */
	private function buildRevisionTimestamp( XMLHelper $xmlHelper, DOMElement $pageNode ): string {
		$lastModificationDate = $xmlHelper->getPropertyValue( 'lastModificationDate', $pageNode );
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );
		return $mwTimestamp;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $pageNode
	 * @return array
	 */
	private function getBodyContentIds( XMLHelper $xmlHelper, DOMElement $pageNode ): array {
		$ids = [];
		$bodyContentEl = $xmlHelper->getElementsFromCollection( 'bodyContents', $pageNode );

		foreach ( $bodyContentEl as $bodyContentElement ) {
			$ids[] = $xmlHelper->getIDNodeValue( $bodyContentElement );
		}
		return $ids;
	}

	/**
	 * @param XMLHelper $xmlHelper
	 * @param DOMElement $element
	 * @param int $spaceId
	 * @return void
	 */
	private function getAttachmentsFromCollection( XMLHelper $xmlHelper, DOMElement $element, int $spaceId ): void {
		$pageId = $xmlHelper->getIDNodeValue( $element );
		if ( !isset( $this->data['analyze-page-id-to-confluence-title-map'][$pageId] ) ) {
			return;
		}
		$confluenceTitle = $this->data['analyze-page-id-to-confluence-title-map'][$pageId];
		if ( !isset( $this->data['analyze-page-id-to-confluence-key-map'][$pageId] ) ) {
			return;
		}
		$confluenceKey = $this->data['analyze-page-id-to-confluence-key-map'][$pageId];
		if ( !isset( $this->data['analyze-pages-titles-map'][$confluenceKey] ) ) {
			return;
		}
		$wikiTitle = $this->data['analyze-pages-titles-map'][$confluenceKey];

		// In case of ERM34465 this seems to be empty because
		// title-attachments and debug-missing-attachment-id-to-filename are empty
		$attachmentRefs = $xmlHelper->getElementsFromCollection( 'attachments', $element );

		foreach ( $attachmentRefs as $attachmentRef ) {
			$attachmentId = $xmlHelper->getIDNodeValue( $attachmentRef );
			if ( in_array( $attachmentId, $this->data['analyze-added-attachment-id'] ) ) {
				continue;
			}
			if ( !isset( $this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId] ) ) {
				continue;
			}
			$attachmentOrigFilename = $this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId];
			if ( isset( $this->data['analyze-attachment-id-to-space-id-map'][$attachmentId] ) ) {
				$attachmentSpaceId = $this->data['analyze-attachment-id-to-space-id-map'][$attachmentId];
			} else {
				$attachmentSpaceId = $spaceId;
			}
			$attachmentTargetFilename = $this->makeAttachmentTargetFilenameFromData(
				$confluenceTitle, $attachmentId, $attachmentSpaceId,
				$attachmentOrigFilename, $wikiTitle, $this->data['global-space-id-to-prefix-map']
			);
			if ( $attachmentTargetFilename === '' ) {
				$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$attachmentId]
					= $attachmentTargetFilename;
				continue;
			}
			if ( !isset( $this->data['analyze-attachment-id-to-reference-map'][$attachmentId] ) ) {
				continue;
			}
			$attachmentReference = $this->data['analyze-attachment-id-to-reference-map'][$attachmentId];

			// In case of ERM34465 no files are added to title-attachments
			$this->data['global-title-attachments'][$wikiTitle][] = $attachmentTargetFilename;
			$this->data['analyze-add-file'][$attachmentTargetFilename] = $attachmentReference;
			$this->data['analyze-title-to-attachment-title'][$wikiTitle] = $attachmentTargetFilename;
			$this->data['analyze-added-attachment-id'][] = $attachmentId;

			$confluenceFileKey = str_replace( ' ', '_', "{$spaceId}---{$confluenceTitle}---{$attachmentOrigFilename}" );

			$this->data['global-filenames-to-filetitles-map'][$confluenceFileKey]
				= $attachmentTargetFilename;
			$this->data['analyze-attachment-id-to-target-filename-map'][$attachmentId]
				= $attachmentTargetFilename;
			if (
				!isset( $this->data['global-attachment-orig-filename-target-filename-map'][$attachmentOrigFilename] )
			) {
				$this->data['global-attachment-orig-filename-target-filename-map'][$attachmentOrigFilename] = [];
			}
			$this->data['global-attachment-orig-filename-target-filename-map'][$attachmentOrigFilename][]
				= $attachmentTargetFilename;
		}
	}

	/**
	 * @param string $pageConfluenceTitle
	 * @param int $attachmentId
	 * @param int $attachmentSpaceId
	 * @param string $attachmentOrigFilename
	 * @param string $containerTitle
	 * @return string
	 */
	private function makeAttachmentTargetFilenameFromData(
		string $pageConfluenceTitle, int $attachmentId, int $attachmentSpaceId,
		string $attachmentOrigFilename, string $containerTitle
	): string {
		$filenameBuilder = new FilenameBuilder( $this->data['global-space-id-to-prefix-map'], null );
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
