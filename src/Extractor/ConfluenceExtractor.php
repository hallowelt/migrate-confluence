<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\CreateDefaultWikisConfig;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PrepareComments;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateAttachmentsTableWithSpaceIdFallback;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBodyContentIdsFallback;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageTemplatesWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Processor\BuildAttachmentDescriptions;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageTemplateContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\UpdateBlogPostCommentsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Processor\UpdatePageCommentsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use SplFileInfo;

class ConfluenceExtractor extends ExtractorBase implements IDestinationPathAware {

	/** @var string */
	private string $dest = '';

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var WikisConfig */
	private WikisConfig $wikisConfig;

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
		$this->workspaceDB = WorkspaceDB::open( $this->dest );
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
		Log::setDatabaseHandler( $this->workspaceDB );
		Log::to( 'db' )->info( sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() ) );
		$this->wikisConfig = new WikisConfig( $this->workspaceDB );

		$writer = new ExtractorDirectDataWriter( $this->workspaceDB );

		$this->buckets->loadFromWorkspace( $this->workspace );

		// Create default wikis config if none exists
		// The preprosessor is placed here to ensure it runs before all other preprocessors
		$defaultWikiConfig = new CreateDefaultWikisConfig(
			$this->workspaceDB,
			$writer
		);
		$defaultWikiConfig->execute();

		// preparation
		$preprocessors = $this->getPreProcessors( $writer );
		foreach ( $preprocessors as $processor ) {
			$processor->execute();
		}

		$processors = $this->getProcessors( $writer );
		foreach ( $processors as $processor ) {
			$processor->execute();
		}

		$postprocessors = $this->getPostProcessors( $writer );
		foreach ( $postprocessors as $postprocessor ) {
			$postprocessor->execute();
		}

		// Perform validity checks
		$this->checkTitles();

		return true;
	}

	/**
	 * @return array
	 */
	private function getPreProcessors( IExtractorDataWriter $writer ): array {
		return [
			new UpdateBodyContentIdsFallback( $this->workspaceDB, $writer ),
			new UpdatePagesTableWithSpaceIdOfHistoryVersions( $this->workspaceDB, $writer ),
			new UpdatePagesTableWithWikiTitle(
				$this->workspaceDB, $writer, $this->migrationConfig, $this->wikisConfig ),
			new UpdateBlogPostsTableWithSpaceIdOfHistoryVersions( $this->workspaceDB, $writer ),
			new UpdateBlogPostsTableWithWikiTitle( $this->workspaceDB, $writer ),
			new UpdatePageTemplatesWithWikiTitle( $this->workspaceDB, $writer ),
			new UpdateAttachmentsTableWithSpaceIdFallback( $this->workspaceDB, $writer ),
			new UpdatePageAttachmentTable( $this->workspaceDB, $writer, $this->migrationConfig ),
			new UpdateBlogPostAttachmentTable( $this->workspaceDB, $writer, $this->migrationConfig ),
			new PopulateAdditionalAttachmentsTable( $this->workspaceDB, $writer, $this->migrationConfig ),
			new PrepareComments( $this->workspaceDB, $writer ),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors( IExtractorDataWriter $writer ): array {
		return [
			new UpdatePageCommentsTableWithWikiTitle( $this->workspaceDB, $writer ),
			new UpdateBlogPostCommentsTableWithWikiTitle( $this->workspaceDB, $writer ),
			new ExtractSpaceDescriptionBodyContents( $this->workspaceDB, $this->workspace, $writer ),
			new ExtractPagesBodyContents( $this->workspaceDB, $this->workspace, $writer ),
			new ExtractBlogPostsBodyContents( $this->workspaceDB, $this->workspace, $writer ),

			new ExtractPageTemplateContents( $this->workspaceDB, $this->workspace, $writer ),
			new ExtractPagesMetaData( $this->workspaceDB, $writer, $this->migrationConfig ),
			new ExtractBlogPostsMetaData( $this->workspaceDB, $writer, $this->migrationConfig ),
			new ExtractAttachmentsMetaData( $this->workspaceDB, $writer, $this->migrationConfig ),
			new BuildAttachmentDescriptions( $this->workspaceDB, $writer ),
		];
	}

	/**
	 * @return array
	 */
	private function getPostProcessors( IExtractorDataWriter $writer ): array {
		return [
			new ExtractCommentsBodyContents( $this->workspaceDB, $this->workspace, $writer ),
		];
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		if ( !empty( $this->workspaceDB->getLogEntriesForStep( 'analyze' ) ) ) {
			Log::to( 'console' )->warning(
				'WARNINGS / ERRORS: Please check logging table in workspaceDB ' .
				'for details about invalid titles and filenames' );
		}

		if ( !empty( $this->workspaceDB->getInvalidPageWikiTitles() ) ) {
			Log::to( 'console' )->warning(
				'INVALID PAGE TITLES DETECTED: Please check page_invalid_titles ' .
				'table in workspaceDB for details' );
		}

		if ( !empty( $this->workspaceDB->getInvalidBlogPostWikiTitles() ) ) {
			Log::to( 'console' )->warning(
				'INVALID BLOG POST TITLES DETECTED: Please check blog_post_invalid_titles ' .
				'table in workspaceDB for details' );
		}

		if ( !empty( $this->workspaceDB->getInvalidAttachmentTitles() ) ) {
			Log::to( 'console' )->warning(
				'INVALID ATTACHMENT TITLES DETECTED: Please check invalid_attachment_titles ' .
				'table in workspaceDB for details' );
		}
	}
}
