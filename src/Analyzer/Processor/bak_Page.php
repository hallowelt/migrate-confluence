<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use SplFileInfo;
use XMLReader;

class Page extends ProcessorBase {

	/** @var array */
	private array $includeSpaceKey;

	/** @var string */
	private string $mainpage;

	/** @var bool */
	private bool $includeHistory;

	/** @var int|null */
	private ?int $spaceId;

	/** @var int|null */
	private ?int $pageId;

	/** @var string */
	private string $confluenceTitle = '';

	/** @var string */
	private string $wikiTitle = '';

	/** @var integer */
	private int $originalVersionId = -1;

	/** @var string */
	private string $revisionTimestamp;

	/**
	 * Undocumented variable
	 *
	 * @var string
	 */
	private string $lastModificationDate;

	private array $bodyContentIds;

	private array $properties;

	private array $collection;

	/**
	 * @param ConfigDB $configDB
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private ConfigDB $configDB,
		private WorkspaceDB $workspaceDB
	) {}

	/**
	 * @inheritDoc
	 */
	public function getRequiredKeys(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$this->pageId = (int)$this->getCDATAValue();
				} else {
					$this->pageId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$this->properties = $this->processPropertyNodes( $this->properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$this->collection = $this->processCollectionNodes( $this->collection );
			}
			$this->xmlReader->next();
		}

		$status = null;
		if ( isset( $this->properties['contentStatus'] ) ) {
			$status = $properties['contentStatus'];
		}
		if ( !$this->includeHistory && ( $status !== 'current' ) ) {
			return;
		}

		$this->spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$this->spaceId = (int)$properties['space'];
		}

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

		$this->originalVersionId = -1;
		if ( isset( $properties['originalVersion'] ) ) {
			$this->originalVersionId = (int)$properties['originalVersion'];
		}
		if ( $this->originalVersionId !== -1 ) {
			return;
		}

		$this->confluenceTitle = $properties['title'] ?? "";
		if ( empty( $this->confluenceTitle ) ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => "Invalid source title"
			];

			return;
		}

		$titleBuilder = new TitleBuilder(
			$this->data['global-space-id-to-prefix-map'],
			$this->data['global-space-id-homepages'],
			$this->data['analyze-page-id-to-parent-page-id-map'],
			$this->data['analyze-page-id-to-confluence-title-map'],
			$this->mainpage
		);

		try {
			$this->wikiTitle = $titleBuilder->buildTitle( $this->spaceId, $this->pageId, $this->confluenceTitle );
		} catch ( InvalidTitleException $ex ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [
				$this->pageId => $ex->getInvalidTitle()
			];
			// We don't want to lose this page. Title can be modified after analyze process
			$this->wikiTitle = $ex->getInvalidTitle();
		}

		if ( $this->wikiTitle === '' ) {
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [ $this->pageId => $this->wikiTitle ];
			return;
		}

		$this->output->writeln( "Add page '$this->wikiTitle' (ID:$this->pageId)" );

		$this->process( $this->confluenceTitle, $properties, $collection );
	
		$this->workspaceDB->addPage(
			$this->pageId,
			$this->spaceId,
			$this->confluenceTitle,
			$this->wikiTitle,
			$this->revisonTimestamp,
			$this->contentStatus,
			$this->originalVersionId,
			$this->bodyContentIds,
			$this->properties,
			$this->collection
		);
	}

	/**
	 * @param string $title
	 * @param array $properties
	 * @param array $collection
	 *
	 * @return void
	 * @throws InvalidTitleException
	 */
	private function process( string $title, array $properties, array $collection ): void {
		/**
		 * Adds data bucket "analyze-pages-titles-map", which contains mapping from page title itself to
		 * full page title.
		 * Full page title contains parent pages and namespace (if it is not general space).
		 *
		 * After testing for title validity and sanitizing titles they will be added to global-pages-titles-map later.
		 * Example:
		 * "Detailed_planning" -> "Dokumentation/Detailed_planning"
		 */
		$pageConfluenceTitle = $title;
		$genericTitleBuilder = new GenericTitleBuilder( [] );
		$pageConfluenceTitle = $genericTitleBuilder
			->appendTitleSegment( $pageConfluenceTitle )->build();
		// We need to preserve the spaceID, so we can properly resolve cross-space links
		// in the `convert` stage
		$pageConfluenceKey = "$this->spaceId---$pageConfluenceTitle";
		// Some normalization
		$pageConfluenceKey = str_replace( ' ', '_', $pageConfluenceKey );

		// Bail out if page object was already handled
		if ( isset( $this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] ) ) {
			return;
		}

		$this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] = $pageConfluenceKey;

		/**
		 * pages-titles-map is used to resolve link targets.
		 * It can be that the pageConfluenceKey is the same between a parent and a child, but pageId is different.
		 * We don't want this duplicates in the pages-titles-map.
		 */
		if ( isset( $this->data['analyze-pages-titles-map'][$pageConfluenceKey] ) ) {
			$this->handleDuplicateConfluenceKeys( $pageConfluenceKey );
			return;
		}

		$this->data['analyze-pages-titles-map'][$pageConfluenceKey] = $this->wikiTitle;

		// Also add pages IDs in Confluence to full page title mapping.
		// It is needed to have enough context on converting stage,
		// to know from filename which page is currently being converted.
		$this->data['analyze-page-id-to-title-map'][$this->pageId] = $this->wikiTitle;
		$this->data['global-page-id-to-space-id'][$this->pageId] = $this->spaceId;

		$this->lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$this->lastModificationDate = $properties['lastModificationDate'];
		}
		$this->revisionTimestamp = $this->buildTimestamp( $this->lastModificationDate );

		$this->bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$this->bodyContentIds = $collection['bodyContents'];
		}

		if ( !empty( $this->bodyContentIds ) ) {
			foreach ( $this->bodyContentIds as $bodyContentId ) {
				// TODO: Add UserImpl-key or directly MediaWiki username
				// (could also be done in `extract` as "metadata" )
				$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
			}
		} else {
			$this->bodyContentIds = [];
			foreach ( $this->data['analyze-body-content-id-to-page-id-map'] as $bodyContentId => $contentPageId ) {
				if ( $this->pageId === $contentPageId ) {
					$this->bodyContentIds[] = $bodyContentId;
					$this->data['global-body-content-id-to-page-id-map'][$bodyContentId] = $this->pageId;
				}
			}
		}

		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$revision = implode( '/', $bodyContentIds ) . "@$version-$revisionTimestamp";

		$this->data['analyze-title-revisions'][$this->wikiTitle][] = $revision;

		// Find attachments
		$this->getAttachmentsFromCollection( $this->spaceId, $collection );
	}

	/**
	 * @param int $spaceId
	 * @param array $collection
	 * @return void
	 */
	private function getAttachmentsFromCollection( int $spaceId, array $collection ): void {
		if ( !isset( $this->data['analyze-page-id-to-confluence-title-map'][$this->pageId] ) ) {
			return;
		}
		$confluenceTitle = $this->data['analyze-page-id-to-confluence-title-map'][$this->pageId];

		if ( !isset( $this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] ) ) {
			return;
		}
		if ( !isset( $this->data['analyze-page-id-to-title-map'][$this->pageId] ) ) {
			return;
		}
		$wikiTitle = $this->data['analyze-page-id-to-title-map'][$this->pageId];

		// In case of ERM34465 this seems to be empty because
		// title-attachments and debug-missing-attachment-id-to-filename are empty
		$attachmentRefs = [];
		if ( isset( $collection['attachments'] ) ) {
			$attachmentRefs = $collection['attachments'];
		}

		foreach ( $attachmentRefs as $attachmentId ) {
			$attachmentId = (int)$attachmentId;
			if ( in_array( $attachmentId, $this->data['analyze-added-attachment-id'] ) ) {
				continue;
			}
			if ( !isset( $this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId] ) ) {
				continue;
			}

			$attachmentOrigFilename = $this->data['analyze-attachment-id-to-orig-filename-map'][$attachmentId];

			$attachmentSpaceId = $spaceId;
			if ( isset( $this->data['analyze-attachment-id-to-space-id-map'][$attachmentId] ) ) {
				$attachmentSpaceId = $this->data['analyze-attachment-id-to-space-id-map'][$attachmentId];
			}

			$attachmentTargetFilename = $this->makeAttachmentTargetFilenameFromData(
				$attachmentId, $attachmentSpaceId,
				$attachmentOrigFilename, $wikiTitle
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
			$this->data['analyze-title-to-attachment-title'][$wikiTitle][] = $attachmentTargetFilename;
			$this->data['analyze-added-attachment-id'][] = $attachmentId;

			$confluenceFileKey = str_replace( ' ', '_', "$spaceId---$confluenceTitle---$attachmentOrigFilename" );

			$this->data['global-filenames-to-filetitles-map'][$confluenceFileKey]
				= $attachmentTargetFilename;

			$this->data['analyze-attachment-id-to-target-filename-map'][$attachmentId]
				= $attachmentTargetFilename;

			$this->data['global-attachment-id-to-confluence-file-key-map'][$attachmentId]
				= $confluenceFileKey;
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
	 * @param int $attachmentId
	 * @param int $attachmentSpaceId
	 * @param string $attachmentOrigFilename
	 * @param string $containerTitle
	 * @return string
	 */
	private function makeAttachmentTargetFilenameFromData(
		int $attachmentId, int $attachmentSpaceId,
		string $attachmentOrigFilename, string $containerTitle
	): string {
		$filenameBuilder = new FilenameBuilder( $this->data['global-space-id-to-prefix-map'], $this->config );
		try {
			$targetName = $filenameBuilder->buildFromAttachmentData(
				$attachmentSpaceId, $attachmentOrigFilename, $containerTitle );
		} catch ( InvalidTitleException $e ) {
			try {
				// Probably it is just too long. Let's try to use a shortened variant
				// This is not ideal, but should be okay as a fallback in most cases.
				$shortwikiTitle = basename( $containerTitle );
				$targetName = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId, $attachmentOrigFilename, $shortwikiTitle );
			} catch ( InvalidTitleException $ex ) {
				$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$attachmentId]
					= $ex->getInvalidTitle();
				$this->logger->error( $ex->getMessage() );
				$targetName = $ex->getInvalidTitle();
			}
		}

		/*
		 * Some attachments do not have a file extension available. We try
		 * to find an extension by looking at the content type, but
		 * sometimes even this won't help... ("octet-stream")
		 */
		$file = new SplFileInfo( $targetName );
		if ( $this->hasNoExplicitFileExtension( $file ) ) {
			$this->logger->debug(
				"Could not find file extension for $attachmentId"
			);
			$targetName .= '.unknown';
		}

		return $targetName;
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 */
	private function hasNoExplicitFileExtension( SplFileInfo $file ): bool {
		if ( $file->getExtension() === '' ) {
			return true;
		}
		// Evil hack for Names like "02.1 Some-Workflow File"
		if ( strlen( $file->getExtension() ) > 10 ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $pageConfluenceKey
	 *
	 * @return void
	 */
	private function handleDuplicateConfluenceKeys( string $pageConfluenceKey ): void {
		if ( !isset( $this->data['analyze-pages-titles-map'][$pageConfluenceKey] ) ) {
			return;
		}

		if ( !isset( $this->data['analyze-pages-titles-duplicates-map'][$pageConfluenceKey] ) ) {
			$this->data['analyze-pages-titles-duplicates-map'][$pageConfluenceKey] = [
				$this->data['analyze-pages-titles-map'][$pageConfluenceKey],
				$this->wikiTitle
			];

			return;
		}

		$this->data['analyze-pages-titles-duplicates-map'][$pageConfluenceKey][] = $this->wikiTitle;
	}
}
