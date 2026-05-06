<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use DOMDocument;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use SplFileInfo;

class ConfluenceExtractor extends ExtractorBase implements IDestinationPathAware {

	/** @var string */
	private string $dest = '';

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

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

		$this->buckets->loadFromWorkspace( $this->workspace );

		$this->extractBodyContents();
		$this->extractPagesMetaData();
		$this->extractBlogPostsMetaData();
		$this->extractAttachmentsMetaData();
	
		return true;
	}

	/**
	 * @return void
	 */
	private function extractBodyContents(): void {
		$currentContentIds = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			if ( isset( $page['page_id'], $page['content_status'] )
				&& strtolower( (string)$page['content_status'] ) === 'current'
			) {
				$currentContentIds[(int)$page['page_id']] = true;
			}
		}

		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			if ( isset( $blogPost['page_id'], $blogPost['content_status'] )
				&& strtolower( (string)$blogPost['content_status'] ) === 'current'
			) {
				$currentContentIds[(int)$blogPost['page_id']] = true;
			}
		}

		if ( $currentContentIds === [] ) {
			return;
		}

		$bodyContents = $this->workspaceDB->getBodyContents();
		$this->doExtractBodyContent( $bodyContents );
		
	}

	/**
	 * @param array $bodyContents
	 * @return void
	 */
	public function doExtractBodyContent( array $bodyContents ): void {
		foreach ( $bodyContents as $bodyContent ) {
			if ( !isset( $bodyContent['body_content_id'], $bodyContent['page_id'] ) ) {
				continue;
			}

			$id = (int)$bodyContent['body_content_id'];
			$pageId = (int)$bodyContent['page_id'];
			if ( !isset( $currentContentIds[$pageId] ) ) {
				continue;
			}

			$bodies = $this->workspaceDB->getBodyForBodyContentId( $id );
			if ( empty( $bodies ) || !isset( $bodies[0] ) ) {
				continue;
			}

			$bodyContentHTML = $this->normalizeBodyContentHTML( (string)$bodies[0] );
			$targetFileName = $this->workspace->saveRawContent( (string)$id, $bodyContentHTML );
			$this->addRevisionContent( (string)$id, $targetFileName );
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

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function extractPagesMetaData(): void {
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$categories = $this->migrationConfig->getCategories();

			if ( isset( $page['page_id'], $page['content_status'] )
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

				// $this->output->writeln( "Add page meta for page {$page['wiki_title']}" );
				// TODO: Add output
			}
		}
	}

	/**
	 * @return void
	 */
	private function extractBlogPostsMetaData(): void {
		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$categories = [];

			if ( isset( $blogPost['page_id'], $blogPost['content_status'] )
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

				// $this->output->writeln( "Add blog post meta for blog post {$blogPost['wiki_title']}" );
				// TODO: Add output
			}
		}
	}

	/**
	 * @return void
	 */
	private function extractAttachmentsMetaData(): void {
		foreach ( $this->workspaceDB->getAttachments() as $attachment ) {
			$categories = [];

			if ( isset( $attachment['page_id'], $attachment['content_status'] )
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

				// $this->output->writeln( "Add attachment meta for attachment {$attachment['wiki_title']}" );
				// TODO: Add output
			}
		}
	}
}
