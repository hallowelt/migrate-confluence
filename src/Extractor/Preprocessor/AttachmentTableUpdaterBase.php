<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\FilenameBuilder;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use SplFileInfo;

/**
 * Base class for preprocessors that populate an attachment table
 * (either page attachments or blog post attachments).
 *
 * Subclasses provide the content-type-specific DB operations while
 * this class owns the shared title-building and collision-resolution logic.
 */
abstract class AttachmentTableUpdaterBase extends ProcessorBase {

	protected const MAX_UNCOLLIDE_ATTEMPTS = 10000;
	protected const UNKNOWN_EXTENSION = '.unknown';

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB, DBLog $dbLog, protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addAttachments();
		$this->checkWikiTitles();
	}

	/**
	 * Returns all content items (pages or blog posts) as arrays with at least
	 * 'page_id' and 'wiki_title' keys.
	 * Subclasses that rely on the default addAttachments() implementation must override this.
	 *
	 * @return array
	 */
	protected function getContentItems(): array {
		return [];
	}

	/**
	 * Returns a human-readable label for the content type, used in log messages.
	 * E.g. "page" or "blog post".
	 *
	 * @return string
	 */
	abstract protected function getContentLabel(): string;

	/**
	 * Checks whether a wiki title already exists in the attachment table.
	 *
	 * @param string $wikiTitle
	 * @return bool
	 */
	abstract protected function checkWikiTitleExists( string $wikiTitle ): bool;

	/**
	 * Persists a new attachment entry to the attachment table.
	 *
	 * @param int $attachmentId
	 * @param int $containerId
	 * @param string $originalFilename
	 * @param string $targetFilename
	 * @return void
	 */
	abstract protected function storeAttachment(
		int $attachmentId, int $containerId, string $originalFilename, string $targetFilename
	): void;

	/**
	 * Returns all stored attachment entries for validity checking.
	 *
	 * @return array
	 */
	abstract protected function getStoredAttachments(): array;

	protected function addAttachments(): void {
		$contentIdToWikiTitleMap = [];
		foreach ( $this->getContentItems() as $item ) {
			if ( !isset( $item['page_id'] ) ) {
				continue;
			}

			$contentId = (int)$item['page_id'];
			$wikiTitle = '';
			if ( isset( $item['wiki_title'] ) ) {
				$wikiTitle = (string)$item['wiki_title'];
			}

			if ( $wikiTitle === '' ) {
				$pageTitle = $this->workspaceDB->getWikiPageTitleFromPageId( $contentId );
				if ( $pageTitle !== null ) {
					$wikiTitle = $pageTitle;
				}
			}

			if ( $wikiTitle === '' ) {
				$blogPostTitle = $this->workspaceDB->getWikiBlogPostTitleFromBlogPostId( $contentId );
				if ( $blogPostTitle !== null ) {
					$wikiTitle = $blogPostTitle;
				}
			}

			if ( $wikiTitle === '' ) {
				continue;
			}

			$contentIdToWikiTitleMap[$contentId] = $wikiTitle;
		}

		if ( $contentIdToWikiTitleMap === [] ) {
			return;
		}

		$filenameBuilder = new FilenameBuilder(
			$this->getSpaceIdToPrefixMapWithConfigOverrides(),
			$this->migrationConfig
		);

		/** @var array<int,array{containerId:int,origFilename:string,wikiTitle:string}> $collected */
		$collected = [];

		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			if (
				!isset( $attachment['attachment_id'] )
				|| !isset( $attachment['space_id'] )
				|| !isset( $attachment['filename'] )
				|| !isset( $attachment['container_id'] )
				|| !isset( $attachment['content_status'] )
			) {
				continue;
			}

			if ( $attachment['content_status'] !== 'current' ) {
				continue;
			}

			$containerId = (int)$attachment['container_id'];
			if ( !isset( $contentIdToWikiTitleMap[$containerId] ) ) {
				continue;
			}

			$attachmentId = (int)$attachment['attachment_id'];
			$attachmentSpaceId = (int)$attachment['space_id'];
			$attachmentOrigFilename = (string)$attachment['filename'];

			$this->writeln(
				"Creating wiki title for attachment ID $attachmentId with title: $attachmentOrigFilename"
			);

			$contentWikiTitle = $contentIdToWikiTitleMap[$containerId];
			if ( str_contains( $contentWikiTitle, ':' ) ) {
				// If string does not contain a colon the page title is located in main namespace of the wiki
				$contentWikiTitle = substr( $contentWikiTitle, strrpos( $contentWikiTitle, ':' ) + 1 );
			}
			$contentWikiTitleParts = explode( '/', $contentWikiTitle );
			$shortContentWikiTitle = end( $contentWikiTitleParts );

			try {
				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortContentWikiTitle,
				);
			} catch ( Exception $fallbackEx ) {
				$this->dbLog->addLogEntry(
					'warning',
					'analyze',
					__CLASS__,
					"Could not build target filename for attachment $attachmentId: "
					. $fallbackEx->getMessage()
				);
			}

			if ( empty( $attachmentWikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for attachment id $attachmentId";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);

				throw new Exception(
					$message
				);
			}

			// Uncollide file title
			$exists = $this->checkWikiTitleExists( $attachmentWikiTitle );
			$counter = 1;
			while ( $exists ) {
				if ( $counter > self::MAX_UNCOLLIDE_ATTEMPTS ) {
					$this->dbLog->addLogEntry(
						'warning',
						'analyze',
						__CLASS__,
						"Could not find unique {$this->getContentLabel()} attachment title for attachment "
						. "$attachmentId after " . (string)self::MAX_UNCOLLIDE_ATTEMPTS . ' attempts'
					);
					continue 2;
				}

				$attachmentWikiTitle = $filenameBuilder->buildFromAttachmentData(
					$attachmentSpaceId,
					$attachmentOrigFilename,
					$shortContentWikiTitle,
					"-(" . (string)$counter . ")"
				);

				if ( empty( $attachmentWikiTitle ) ) {
					$message = "TitleCompressor delivers empty wiki title for "
					 . "attachment id $attachmentId while uncolliding";

					$this->dbLog->addLogEntry(
						'error',
						'extract',
						__CLASS__,
						$message
					);

					throw new Exception(
						$message
					);
				}

				$exists = $this->checkWikiTitleExists( $attachmentWikiTitle );
				$counter++;
			}

			$file = new SplFileInfo( $attachmentWikiTitle );
			if ( $file->getExtension() === '' || strlen( $file->getExtension() ) > 10 ) {
				$attachmentWikiTitle .= self::UNKNOWN_EXTENSION;
			}

			$collected[$attachmentId] = [
				'containerId' => $containerId,
				'origFilename' => $attachment['filename'],
				'wikiTitle' => $attachmentWikiTitle,
			];
		}

		$collected = $this->compressWikiTitles( $collected );

		foreach ( $collected as $attachmentId => $data ) {
			$this->writeln(
				"Add {$this->getContentLabel()} attachment for attachment ID $attachmentId"
				. " with title: {$data['wikiTitle']}"
			);

			$this->storeAttachment(
				$attachmentId,
				$data['containerId'],
				$data['origFilename'],
				$data['wikiTitle']
			);
			$this->addAttachmentDescription( $attachmentId, $data['wikiTitle'], $data['origFilename'] );
		}
	}

	/**
	 * Builds the space_id => prefix map used for attachment title generation.
	 * Configured space-prefix values override DB prefixes by matching space keys.
	 *
	 * @return array
	 */
	protected function getSpaceIdToPrefixMapWithConfigOverrides(): array {
		$spaceIdToPrefixMap = $this->workspaceDB->getMapSpaceIdToPrefix();
		$spaceIdToKeyMap = $this->workspaceDB->getMapSpaceIdToKey();

		foreach ( $spaceIdToKeyMap as $spaceId => $spaceKey ) {
			$configPrefix = $this->migrationConfig->getPrefixFromSpaceKeyToPrefixMap( (string)$spaceKey );
			if ( $configPrefix === null ) {
				continue;
			}

			// Keep backward compatibility for plain namespace names like "MYTEST".
			if ( $configPrefix !== '' && strpos( $configPrefix, ':' ) === false ) {
				$configPrefix .= ':';
			}

			$spaceIdToPrefixMap[(int)$spaceId] = $configPrefix;
		}

		return $spaceIdToPrefixMap;
	}

	/**
	 * If the original filename is not preserved in the wiki title (e.g. due to abbreviation),
	 * generate a file description wikitext and store it in the database.
	 */
	protected function addAttachmentDescription(
		int $attachmentId, string $targetTitle, string $originalFilename
	): void {
		if ( $originalFilename === '' ) {
			return;
		}
		$normalized = str_replace( [ ' ', '/' ], '_', $originalFilename );
		$normalized = preg_replace( '/_+/', '_', $normalized ) ?? $normalized;

		$colonPos = strpos( $targetTitle, ':' );
		$localTarget = $colonPos !== false ? substr( $targetTitle, $colonPos + 1 ) : $targetTitle;

		// Case-insensitive: WindowsFilename applies ucfirst() which must not cause false positives.
		if ( stripos( $localTarget, $normalized ) !== false ) {
			return;
		}

		$quotedFileName = htmlspecialchars( $originalFilename, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		$description = "Original file name: <nowiki>$quotedFileName</nowiki>\n"
			. "{{DISPLAYTITLE:$quotedFileName|noerror}}";
		$this->workspaceDB->addAttachmentDescription( $attachmentId, $description );
	}

	/**
	 * Apply TitleCompressor to shorten attachment wiki titles that exceed 255 characters.
	 *
	 * @param array<int,array{containerId:int,origFilename:string,wikiTitle:string}> $collected
	 * @return array<int,array{containerId:int,origFilename:string,wikiTitle:string}>
	 */
	protected function compressWikiTitles( array $collected ): array {
		$attachmentIdToWikiTitleMap = array_map(
			static fn ( array $data ) => $data['wikiTitle'],
			$collected
		);

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $attachmentIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedMap = $applyCompressedTitles->toMapValues( $attachmentIdToWikiTitleMap );

		foreach ( $compressedMap as $attachmentId => $compressedTitle ) {
			$collected[$attachmentId]['wikiTitle'] = $compressedTitle;
		}

		return $collected;
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$validityChecker = new TitleValidityChecker();
		foreach ( $this->getStoredAttachments() as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$wikiTitle = $attachment['target_attachment_filename'];
			if ( !$validityChecker->hasValidLength( $wikiTitle ) ) {
				$this->workspaceDB->addInvalidAttachmentTitle(
					$attachmentId,
					$wikiTitle,
					'Attachment title contains too many characters (>255)'
				);
			}
		}
	}
}
