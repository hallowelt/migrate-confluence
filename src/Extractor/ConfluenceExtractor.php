<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostAttachmentTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBodyContentIdsFallback;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageTemplatesWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractAttachmentsMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageTemplateContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WikiConfig;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;

class ConfluenceExtractor extends ExtractorBase implements IDestinationPathAware, IOutputAwareInterface {

	/** @var string */
	private string $dest = '';

	/** @var Output|null */
	private ?Output $output = null;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var DBLog */
	private DBLog $dbLog;

	/** @var WikiConfig */
	private WikiConfig $wikiConfig;

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
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
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
	 * @return void
	 */
	private function initWikiConfig(): void {
		$wikiConfig = [];
		if ( isset( $this->config['wiki-config'] ) ) {
			$wikiConfig = $this->config['wiki-config'];
		}
		foreach ( $wikiConfig as $config ) {
			$this->workspaceDB->addWikiConfig(
				$config['space-key'],
				$config['wiki-name'],
				$config['wiki-namespace'],
				$config['wiki-root-page']
			);
		}

		$this->wikiConfig = new WikiConfig( $this->workspaceDB );
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doExtract( SplFileInfo $file ): bool {
		$this->initMigrationConfig();
		$this->initWorkspaceDB();
		$this->initWikiConfig();
		$this->initDBLog();

		$this->buckets->loadFromWorkspace( $this->workspace );

		// preparation
		$preprocessors = $this->getPreprocessors();
		foreach ( $preprocessors as $processor ) {
			if ( $this->output ) {
				$processor->setOutput( $this->output );
			}
			$processor->execute();
		}

		// Perform validity checks
		$this->checkTitles();

		// extraction
		$processors = $this->getProcessors();
		foreach ( $processors as $processor ) {
			$processor->execute();
		}

		return true;
	}

	/**
	 * @return array
	 */
	private function getPreprocessors(): array {
		return [
			new UpdateBodyContentIdsFallback( $this->workspaceDB, $this->dbLog ),
			new UpdatePagesTableWithSpaceIdOfHistoryVersions( $this->workspaceDB, $this->dbLog ),
			new UpdatePagesTableWithWikiTitle( $this->workspaceDB, $this->dbLog, $this->migrationConfig, $this->wikiConfig ),
			new UpdateBlogPostsTableWithSpaceIdOfHistoryVersions( $this->workspaceDB, $this->dbLog ),
			new UpdateBlogPostsTableWithWikiTitle( $this->workspaceDB, $this->dbLog, $this->wikiConfig ),
			new UpdatePageTemplatesWithWikiTitle( $this->workspaceDB, $this->dbLog, $this->wikiConfig ),
			new UpdatePageAttachmentTable( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new UpdateBlogPostAttachmentTable( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new PopulateAdditionalAttachmentsTable( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [];
		return [
			new ExtractSpaceDescriptionBodyContents( $this->workspaceDB, $this->workspace, $this->dbLog ),
			new ExtractPagesBodyContents( $this->workspaceDB, $this->workspace, $this->dbLog ),
			new ExtractBlogPostsBodyContents( $this->workspaceDB, $this->workspace, $this->dbLog ),
			new ExtractCommentsBodyContents( $this->workspaceDB, $this->workspace, $this->dbLog ),
			new ExtractPageTemplateContents( $this->workspaceDB, $this->workspace, $this->dbLog ),
			new ExtractPagesMetaData( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new ExtractBlogPostsMetaData( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new ExtractAttachmentsMetaData( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
		];
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		if ( !empty( $this->dbLog->getLogEntriesForStep( 'analyze' ) ) ) {
			$this->writeln( "\n\nWARNINGS / ERRORS:\n" );
			$this->writeln(
				"\nPlease check logging table in workspaceDB for details about invalid titles and filenames\n\n"
			);
		}

		if ( !empty( $this->workspaceDB->getInvalidPageWikiTitles() ) ) {
			$this->writeln( "\n\INVALID PAGE TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check page_invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( !empty( $this->workspaceDB->getInvalidBlogPostWikiTitles() ) ) {
			$this->writeln( "\n\INVALID BLOG POST TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check blog_post_invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( !empty( $this->workspaceDB->getInvalidAttachmentTitles() ) ) {
			$this->writeln( "\n\INVALID ATTACHMENT TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check invalid_attachment_titles table in workspaceDB for details\n\n"
			);
		}
	}

	/**
	 * @param string $text
	 * @param int $options
	 * @return void
	 */
	private function writeln( string $text, int $options = Output::OUTPUT_NORMAL ): void {
		if ( $this->output instanceof Output ) {
			$this->output->writeln( $text, $options );
		}
	}
}
