<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\TitleBuilder;
use SplFileInfo;
use XMLReader;

class Page extends ProcessorBase {

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

	/* @var string */
	private $targetTitle = '';
	
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
	public function getRequiredKeys(): array{
		return [
			'global-space-id-to-prefix-map',
			'analyze-space-id-to-space-key-map',
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
			'global-body-contents-to-pages-map',
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
	public function doExecute(): void {
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$this->pageId = $this->getCDATAValue();
				} else {
					$this->pageId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		$status = null;
		if ( isset( $properties['contentStatus'] ) ) {
			$status = $properties['contentStatus'];
		}
		if ( !$this->includeHistory && ( $status !== 'current' ) ) {
			return;
		}

		$this->spaceId = null;
		if ( isset( $properties['space'] ) ) {
			$this->spaceId = $properties['space'];
		}
		if ( $this->spaceId === null ) {
			return;
		}

		if ( !isset( $this->data['analyze-space-id-to-space-key-map'][$this->spaceId] ) ) {
			return;
		}
		$spaceKey = $this->data['analyze-space-id-to-space-key-map'][$this->spaceId];

		if ( !empty( $this->includeSpaceKey )
			&& !in_array( strtolower( $spaceKey ), $this->includeSpaceKey )
		) {
			return;
		}

		$originalVersionID = null;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionID = $properties['originalVersion'];
		}
		if ( $originalVersionID !== null ) {
			return;
		}

		$title = null;
		if ( isset( $properties['title'] ) ) {
			$title = $properties['title'];
		}

		$titleBuilder = new TitleBuilder(
			$this->data['global-space-id-to-prefix-map'],
			$this->data['global-space-id-homepages'],
			$this->data['analyze-page-id-to-parent-page-id-map'],
			$this->data['analyze-page-id-to-confluence-title-map'],
			$this->mainpage
		);
		try {
			$this->targetTitle = $titleBuilder->buildTitle( $this->spaceId, $this->pageId, $title );
		} catch ( InvalidTitleException $ex ) {
			/*
			$this->customBuckets->addData(
				'debug-analyze-invalid-titles-page-id-to-title',
				$this->pageId, $ex->getInvalidTitle()
			);
			*/
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [ $this->pageId => $ex->getInvalidTitle() ];
			// We don't want to loose this page. Title can be modified after analyze process
			$this->targetTitle = $ex->getInvalidTitle();
		}

		if ( $this->targetTitle === '' ) {
			//$this->customBuckets->addData( 'debug-analyze-invalid-titles-page-id-to-title', $this->pageId, $targetTitle );
			$this->data['debug-analyze-invalid-titles-page-id-to-title'][] = [ $this->pageId =>$this->targetTitle ];
			return;
		}

		$this->output->writeln( "Add page '$this->targetTitle' (ID:$this->pageId)" );

		$this->process( $title, $properties, $collection );
	}

	/**
	 * @param string $title
	 * @param array $properties
	 * @param array $collection
	 * @return void
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
		$pageConfluenceTitle = "$this->spaceId---{$pageConfluenceTitle}";
		// Some normalization
		$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
		/*
		$this->customBuckets->addData(
			'analyze-page-id-to-confluence-key-map',
			$this->pageId, $pageConfluenceTitle, false, true
		);
		*/
		$this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] = $pageConfluenceTitle;

		//$this->customBuckets->addData( 'analyze-pages-titles-map', $pageConfluenceTitle, $this->targetTitle, false, true );
		$this->data['analyze-pages-titles-map'][$pageConfluenceTitle] = $this->targetTitle;
		// Also add pages IDs in Confluence to full page title mapping.
		// It is needed to have enough context on converting stage,
		// to know from filename which page is currently being converted.
		
		//$this->customBuckets->addData( 'analyze-page-id-to-title-map', $this->pageId, $this->targetTitle, false, true );
		$this->data['analyze-page-id-to-title-map'][$this->pageId] = $this->targetTitle;
		//$this->buckets->addData( 'global-page-id-to-space-id', $this->pageId, $this->spaceId, false, true );
		$this->data['global-page-id-to-space-id'][$this->pageId] = $this->spaceId;

		$lastModificationDate = 'lastModificationDate';
		if ( isset( $properties['title'] ) ) {
			$lastModificationDate = $properties['title'];
		}
		$revisionTimestamp = $this->buildRevisionTimestamp( $lastModificationDate );

		$bodyContentIds = [];
		if ( isset( $properties['bodyContents'] ) ) {
			$bodyContentIds = $properties['bodyContents'];
		}
		if ( !empty( $bodyContentIds ) ) {
			foreach ( $bodyContentIds as $bodyContentId ) {
				// TODO: Add UserImpl-key or directly MediaWiki username
				// (could also be done in `extract` as "metadata" )
				
				//$this->buckets->addData( 'global-body-contents-to-pages-map', $bodyContentId, $this->pageId, false, true );
				$this->data['global-body-contents-to-pages-map'][$bodyContentId] = $this->pageId;
			}
		} else {
			$bodyContentIds = [];
			foreach ( $this->data['analyze-body-content-id-to-page-id-map'] as $bodyContentId => $contentPageId ) {
				if ( $this->pageId === $contentPageId ) {
					$bodyContentIds[] = $bodyContentId;

					/*
					$this->buckets->addData(
						'global-body-contents-to-pages-map',
						$bodyContentId,
						$this->pageId,
						false,
						true
					);
					*/
					$this->data['global-body-contents-to-pages-map'][$bodyContentId] = $this->pageId;
				}
			}
		}

		$version = '';
		if ( isset( $version['version'] ) ) {
			$version = $properties['version'];
		}
		$revision = implode( '/', $bodyContentIds ) . "@{$version}-{$revisionTimestamp}";
		//$this->addAnalyzerTitleRevision( $this->targetTitle, $revision );
		$this->data['analyze-title-revisions'][$this->targetTitle][] = $revision;

		// Find attachments
		$this->getAttachmentsFromCollection( $this->spaceId, $properties, $collection );
	}

	/**
	 * @param string $lastModificationDate
	 * @return string
	 */
	private function buildRevisionTimestamp( string $lastModificationDate ): string {
		$time = strtotime( $lastModificationDate );
		$mwTimestamp = date( 'YmdHis', $time );
		return $mwTimestamp;
	}

	/**
	 * @param int $spaceId
	 * @param array $properties
	 * @param array $collection
	 * @return void
	 */
	private function getAttachmentsFromCollection( int $spaceId, array $properties, array $collection ): void {
		if ( !isset( $this->data['analyze-page-id-to-confluence-title-map'][$this->pageId] ) ) {
			return;
		}
		$confluenceTitle = $this->data['analyze-page-id-to-confluence-title-map'][$this->pageId];
		if ( !isset( $this->data['analyze-page-id-to-confluence-key-map'][$this->pageId] ) ) {
			return;
		}
		$confluenceKey = $this->data['analyze-page-id-to-confluence-key-map'][$this->pageId];
		if ( !isset( $this->data['analyze-pages-titles-map'][$confluenceKey] ) ) {
			return;
		}
		$wikiTitle = $this->data['analyze-pages-titles-map'][$confluenceKey];

		// In case of ERM34465 this seems to be empty because
		// title-attachments and debug-missing-attachment-id-to-filename are empty
		$attachmentRefs = [];
		if ( isset( $collection['attachments'] ) ) {
			$attachmentRefs = $collection['attachments'];
		}
		
		foreach ( $attachmentRefs as $attachmentId ) {
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
				/*
				$this->customBuckets->addData(
					'debug-analyze-invalid-titles-attachment-id-to-title',
					$attachmentId, $attachmentTargetFilename
				);
				*/
				$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$attachmentId] = $attachmentTargetFilename;
				continue;
			}
			if ( !isset( $this->data['analyze-attachment-id-to-reference-map'][$attachmentId] ) ) {
				continue;
			}
			$attachmentReference = $this->data['analyze-attachment-id-to-reference-map'][$attachmentId];

			// In case of ERM34465 no files are added to title-attachments
			//$this->addTitleAttachment( $wikiTitle, $attachmentTargetFilename );
			$this->data['global-title-attachments'][$wikiTitle][] = $attachmentTargetFilename;
			//$this->addFile( $attachmentTargetFilename, $attachmentReference );
			$this->data['analyze-add-file'][$attachmentTargetFilename] = $attachmentReference;
			/*
			$this->customBuckets->addData(
				'analyze-title-to-attachment-title',
				$wikiTitle, $attachmentTargetFilename, false, true
			);
			*/
			$this->data['analyze-title-to-attachment-title'][$wikiTitle] = $attachmentTargetFilename;
			$this->data['analyze-added-attachment-id'][] = $attachmentId;

			$confluenceFileKey = str_replace( ' ', '_', "{$spaceId}---{$confluenceTitle}---{$attachmentOrigFilename}" );
			/*
			$this->buckets->addData(
				'global-filenames-to-filetitles-map',
				$confluenceFileKey,
				$attachmentTargetFilename,
				false,
				true
			);

			$this->customBuckets->addData(
				'analyze-attachment-id-to-target-filename-map',
				$attachmentId,
				$attachmentTargetFilename
			);

			$this->buckets->addData(
				'global-attachment-orig-filename-target-filename-map',
				$attachmentOrigFilename,
				$attachmentTargetFilename
			);
			*/
			$this->data['global-filenames-to-filetitles-map'][$confluenceFileKey] = $attachmentTargetFilename;
			$this->data['analyze-attachment-id-to-target-filename-map'][$attachmentId] = $attachmentTargetFilename;
			$this->data['global-attachment-orig-filename-target-filename-map'][$attachmentOrigFilename] = $attachmentTargetFilename;
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
				/*
				$this->customBuckets->addData(
					'debug-analyze-invalid-titles-attachment-id-to-title',
					$attachmentId, $ex->getInvalidTitle()
				);
				*/
				$this->data['debug-analyze-invalid-titles-attachment-id-to-title'][$attachmentId] = $ex->getInvalidTitle();
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
		//$this->buckets->addData( 'global-filenames-to-filetitles-map', $fileKey, $targetName, false, true );
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
