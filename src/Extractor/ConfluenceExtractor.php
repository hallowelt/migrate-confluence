<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use SplFileInfo;

class ConfluenceExtractor extends ExtractorBase implements IDestinationPathAware {

	/** @var string */
	private string $dest = '';

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var DBLog */
	private DBLog $dbLog;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );
	}

	/**
	 * @inheritDoc
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @return void
	 */
	private function initWorkspaceDB(): void {
		$this->workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
	}

	/**
	 * @return void
	 */
	private function initDBLog(): void {
		$this->dbLog = new DBLog( $this->workspaceDB );
		$this->dbLog->addLogEntry(
			'info',
			'extract',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @return void
	 */
	private function initMigrationConfig(): void {
		$advancedConfig = [];
		if ( isset( $this->config['config'] ) ) {
			$advancedConfig = $this->config['config'];
		}
		$this->migrationConfig = new MigrationConfig( $advancedConfig );
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doExtract( SplFileInfo $file ): bool {
		$this->initMigrationConfig();
		$this->initWorkspaceDB();
		$this->initDBLog();

		$this->buckets->loadFromWorkspace( $this->workspace );

		$this->extractSpaceDescriptionBodyContents();
		$this->extractPagesBodyContents();
		$this->extractBlogPostsBodyContents();
		$this->extractCommentsBodyContents();
		$this->extractTemplateContents();
		$this->extractPagesMetaData();
		$this->extractBlogPostsMetaData();
		$this->extractAttachmentsMetaData();

		return true;
	}

	/**
	 * @return void
	 */
	private function extractSpaceDescriptionBodyContents(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getSpaceDescriptions() as $spaceDescription ) {
			if ( isset( $spaceDescription['space_description_id'] ) && isset( $spaceDescription['content_status'] )
				&& strtolower( (string)$spaceDescription['content_status'] ) === 'current'
			) {
				$currentContentIds[] = (int)$spaceDescription['space_description_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @return void
	 */
	private function extractPagesBodyContents(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			if ( isset( $page['page_id'] ) && isset( $page['content_status'] )
				&& strtolower( (string)$page['content_status'] ) === 'current'
			) {
				$currentContentIds[] = (int)$page['page_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @return void
	 */
	private function extractBlogPostsBodyContents(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			if ( isset( $blogPost['page_id'] ) && isset( $blogPost['content_status'] )
				&& strtolower( (string)$blogPost['content_status'] ) === 'current'
			) {
				$currentContentIds[] = (int)$blogPost['page_id'];
			}
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @return void
	 */
	private function extractCommentsBodyContents(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getComments() as $comment ) {
			if ( !isset( $comment['comment_id'] )
				|| !isset( $comment['content_status'] )
				|| !isset( $comment['content_class'] )
			) {
				continue;
			}

			// Comments composer currently handles page-level comments only.
			if ( strtolower( (string)$comment['content_status'] ) !== 'current'
				|| (string)$comment['content_class'] !== 'Page'
			) {
				continue;
			}

			$currentContentIds[] = (int)$comment['comment_id'];
		}

		$this->doExtractBodyContent( $currentContentIds );
	}

	/**
	 * @param array $currentContentIds
	 * @return void
	 */
	public function doExtractBodyContent( array $currentContentIds ): void {
		$currentContentIds = array_values( array_unique( $currentContentIds ) );

		if ( $currentContentIds === [] ) {
			return;
		}

		foreach ( $currentContentIds as $currentContentId ) {
			$bodyContentIds = $this->workspaceDB->getBodyContentIdsForContentId( $currentContentId );
			foreach ( $bodyContentIds as $bodyContentId ) {
				$body = $this->workspaceDB->getBodyContentBodyByBodyContentId( $bodyContentId );
				if ( $body === null ) {
					continue;
				}

				$bodyContentHTML = $this->normalizeBodyContentHTML( $body );
				$targetFileName = $this->workspace->saveRawContent( (string)$bodyContentId, $bodyContentHTML );

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Extract body content to $targetFileName"
				);
			}
		}
	}

	/**
	 * Extract template content and save as raw content for conversion.
	 *
	 * @return void
	 */
	private function extractTemplateContents(): void {
		foreach ( $this->workspaceDB->getPageTemplateContents() as $templateContent ) {
			$templateId = (int)$templateContent['template_id'];
			$content = $templateContent['content'] ?? '';
			if ( $content === '' ) {
				continue;
			}

			$bodyContentHTML = $this->normalizeBodyContentHTML( $content );
			$rawName = 'pt_' . (string)$templateId;
			$this->workspace->saveRawContent( $rawName, $bodyContentHTML );
		}
	}

	/**
	 * @return void
	 */
	private function extractPagesMetaData(): void {
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$categories = $this->migrationConfig->getCategories();

			if ( isset( $page['page_id'] ) && isset( $page['content_status'] )
				&& strtolower( (string)$page['content_status'] ) === 'current'
			) {
				if ( !isset( $page['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $page['collection']['labellings'];
				foreach ( $labellings as $labellingId ) {
					$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
					if ( $labelling === null || !isset( $labelling['label_id'] ) ) {
						continue;
					}
					$labelId = (int)$labelling['label_id'];
					$label = $this->workspaceDB->getLabelById( $labelId );
					if ( $label === null || !isset( $label['name'] ) ) {
						continue;
					}

					$categories[] = $label['name'];
				}

				$categories = array_unique( $categories );

				$this->workspaceDB->addPageMeta(
					(int)$page['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add page meta for page {$page['wiki_title']}"
				);
			}
		}
	}

	/**
	 * @return void
	 */
	private function extractBlogPostsMetaData(): void {
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$categories = [];

			if ( isset( $blogPost['page_id'] ) && isset( $blogPost['content_status'] )
				&& strtolower( (string)$blogPost['content_status'] ) === 'current'
			) {
				if ( !isset( $blogPost['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $blogPost['collection']['labellings'];
				foreach ( $labellings as $labellingId ) {
					$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
					if ( $labelling === null || !isset( $labelling['label_id'] ) ) {
						continue;
					}
					$labelId = (int)$labelling['label_id'];
					$label = $this->workspaceDB->getLabelById( $labelId );
					if ( $label === null || !isset( $label['name'] ) ) {
						continue;
					}

					$categories[] = $label['name'];
				}

				$this->workspaceDB->addBlogPostMeta(
					(int)$blogPost['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add blog post meta for page {$blogPost['wiki_title']}"
				);
			}
		}
	}

	/**
	 * @return void
	 */
	private function extractAttachmentsMetaData(): void {
		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			$categories = [];

			if ( isset( $attachment['page_id'] ) && isset( $attachment['content_status'] )
				&& strtolower( (string)$attachment['content_status'] ) === 'current'
			) {
				if ( !isset( $attachment['collection']['labellings'] ) ) {
					continue;
				}

				$labellings = $attachment['collection']['labellings'];
				foreach ( $labellings as $labellingId ) {
					$labelling = $this->workspaceDB->getLabellingById( (int)$labellingId );
					if ( $labelling === null || !isset( $labelling['label_id'] ) ) {
						continue;
					}
					$labelId = (int)$labelling['label_id'];
					$label = $this->workspaceDB->getLabelById( $labelId );
					if ( $label === null || !isset( $label['name'] ) ) {
						continue;
					}

					$categories[] = $label['name'];
				}

				$this->workspaceDB->addAttachmentMeta(
					(int)$attachment['page_id'],
					[
						'categories' => $categories
					]
				);

				$this->dbLog->addLogEntry(
					'info', 'extract', __METHOD__, "Add attachment meta for attachment {$attachment['wiki_title']}"
				);
			}
		}
	}

	/**
	 * @param string $rawValue
	 * @return string
	 */
	private function normalizeBodyContentHTML( string $rawValue ): string {
		// For a strange reason the CDATA blocks are not closed properly...
		$fixedValue = str_replace( ']] >', ']]>', $rawValue );
		return '<html><body>' . $fixedValue . '</body></html>';
	}
}
