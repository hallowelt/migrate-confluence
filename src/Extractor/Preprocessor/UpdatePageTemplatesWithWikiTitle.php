<?php

namespace HalloWelt\MigrateConfluence\Extractor\Preprocessor;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\ApplyCompressedTitle;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleCompressor;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;

/**
 */
class UpdatePageTemplatesWithWikiTitle extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
		private WikiConfig $wikiConfig
	) {
		parent::__construct( $workspaceDB, $dbLog );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->updateWikiTitles();
		$this->checkWikiTitles();
	}

	/**
	 * @return void
	 *
	 * @throws Exception
	 */
	private function updateWikiTitles(): void {
		$pageTemplates = $this->workspaceDB->getPageTemplates();
		$templateIdToWikiTitleMap = [];

		foreach ( $pageTemplates as $pageTemplate ) {
			if ( !isset( $pageTemplate['template_id'] ) ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					'Skipping page template without template_id while updating wiki titles'
				);
				continue;
			}

			$templateId = (int)$pageTemplate['template_id'];

			if ( !isset( $pageTemplate['space_id'] ) || !isset( $pageTemplate['confluence_title'] ) ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Skipping page template $templateId while updating wiki titles: missing space_id or confluence_title"
				);
				continue;
			}

			if ( isset( $pageTemplate['wiki_title'] ) && $pageTemplate['wiki_title'] !== '' ) {
				continue;
			}

			$spaceId = (int)$pageTemplate['space_id'];
			$confluenceTitle = (string)$pageTemplate['confluence_title'];

			$this->writeln(
				"Creating wiki title for page template ID $templateId with confluence title '$confluenceTitle'"
			);

			try {
				$wikiTitle = $this->buildTemplateTitle( $confluenceTitle, $spaceId );
				$templateIdToWikiTitleMap[$templateId] = $wikiTitle;
			} catch ( InvalidTitleException $e ) {
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Page Template with ID $templateId has invalid title '$confluenceTitle': " . $e->getMessage()
				);

				$this->workspaceDB->addInvalidPageTemplateTitle(
					$templateId,
					'',
					"Page Template with ID $templateId has invalid title '$confluenceTitle': " . $e->getMessage()
				);
				continue;
			}

			if ( empty( $wikiTitle ) ) {
				$message = "TitleBuilder delivers empty wiki title for page template $confluenceTitle (template id $templateId)";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);

				throw new Exception( $message );
			}
		}

		if ( $templateIdToWikiTitleMap === [] ) {
			$this->dbLog->addLogEntry(
				'warning',
				'extract',
				__CLASS__,
				'Could not find page template with wiki title'
			);
			return;
		}

		$titleCompressor = new TitleCompressor();
		$compressedTitlesMap = $titleCompressor->execute( $templateIdToWikiTitleMap );
		$applyCompressedTitles = new ApplyCompressedTitle( $compressedTitlesMap );
		$compressedTemplateIdToWikiTitleMap = $applyCompressedTitles->toMapValues( $templateIdToWikiTitleMap );

		foreach ( $compressedTemplateIdToWikiTitleMap as $templateId => $wikiTitle ) {
			if ( empty( $wikiTitle ) ) {
				$message = "TitleCompressor delivers empty wiki title for page template id $templateId";

				$this->dbLog->addLogEntry(
					'error',
					'extract',
					__CLASS__,
					$message
				);
				throw new Exception( $message );
			}

			$this->writeln(
				"Updated wiki title for page template ID $templateId with title: $wikiTitle"
			);
			$this->workspaceDB->updatePageTemplateWikiTitle( (int)$templateId, $wikiTitle );
		}
	}

	/**
	 * @return void
	 */
	private function checkWikiTitles(): void {
		$titles = [];
		foreach ( $this->workspaceDB->getPageTemplates() as $pageTemplate ) {
			$title = '';
			$templateId = $pageTemplate['template_id'];
			if ( isset( $pageTemplate['wiki_title'] ) && $pageTemplate['wiki_title'] !== '' ) {
				$title = (string)$pageTemplate['wiki_title'];
			}

			if ( $title !== '' ) {
				$titles[$templateId] = $title;
			}
		}

		$validityChecker = new TitleValidityChecker();

		foreach ( $titles as $templateId => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->workspaceDB->addInvalidPageTemplateTitle(
					$templateId, $title, 'Title ends with invalid character'
				);
			}

			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->workspaceDB->addInvalidPageTemplateTitle(
						$templateId, $title, 'Title contains multiple colons'
					);
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->workspaceDB->addInvalidPageTemplateTitle(
						$templateId, $title, 'Invalid namespace character detected'
					);
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->workspaceDB->addInvalidPageTemplateTitle(
						$templateId, $title, 'Title contains too many characters (>255)'
					);
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->workspaceDB->addInvalidPageTemplateTitle(
						$templateId, $title, 'Title contains too many characters (>255)'
					);
				}
			}
		}
	}

	/**
	 * @param string $name
	 * @param int|null $spaceId
	 * @return string
	 * @throws InvalidTitleException
	 */
	private function buildTemplateTitle( string $name, ?int $spaceId ): string {
		$builder = new GenericTitleBuilder( $this->workspaceDB->getMapSpaceIdToPrefix() );
		$builder->setNamespace( GenericTitleBuilder::NS_TEMPLATE );

		$spaces = $this->workspaceDB->getMapSpaceIdToPrefix();
		if ( isset( $spaces[$spaceId] ) ) {
			$spacePrefix = $spaces[$spaceId];
			$colonPos = strpos( $spacePrefix, ':' );
			if ( $colonPos !== false ) {
				$spacePrefix = substr( $spacePrefix, 0, $colonPos );
			}
			if ( $spacePrefix !== '' ) {
				$builder->appendTitleSegment( $spacePrefix );
			}
		}

		$builder->appendTitleSegment( $name );
		return $builder->build();
	}
}
