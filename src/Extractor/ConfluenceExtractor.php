<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBlogPostsTableWithWikiTitle;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdateBodyContentIdsFallback;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePageAttachmentTable;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithSpaceIdOfHistoryVersions;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\UpdatePagesTableWithWikiTitle;
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
use HalloWelt\MigrateConfluence\Utility\TitleValidityChecker;
use HalloWelt\MigrateConfluence\Utility\Version;
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
	 * @param SplFileInfo $file
	 * @return bool
	 */
	protected function doExtract( SplFileInfo $file ): bool {
		$this->initMigrationConfig();
		$this->initWorkspaceDB();
		$this->initDBLog();

		$this->buckets->loadFromWorkspace( $this->workspace );

		// preparation
		$preprocessors = $this->getPreprocessors();
		foreach ( $preprocessors as $processor ) {
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
			new UpdatePagesTableWithWikiTitle( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new UpdateBlogPostsTableWithSpaceIdOfHistoryVersions( $this->workspaceDB, $this->dbLog ),
			new UpdateBlogPostsTableWithWikiTitle( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new UpdatePageAttachmentTable( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
			new PopulateAdditionalAttachmentsTable( $this->workspaceDB, $this->dbLog, $this->migrationConfig ),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
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
		$this->writeln(
			"Validating titles of pages, blog posts and attachments. This may take a while for large instances..."
		);

		$titles = [];
		foreach ( $this->workspaceDB->getPages() as $page ) {
			$title = '';
			$pageId = $page['page_id'];
			if ( isset( $page['wiki_title'] ) && $page['wiki_title'] !== '' ) {
				$title = (string)$page['wiki_title'];
			} elseif ( isset( $page['confluence_title'] ) ) {
				$title = (string)$page['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		foreach ( $this->workspaceDB->getBlogPosts() as $blogPost ) {
			$title = '';
			$pageId = $blogPost['page_id'];
			if ( isset( $blogPost['wiki_title'] ) && $blogPost['wiki_title'] !== '' ) {
				$title = (string)$blogPost['wiki_title'];
			} elseif ( isset( $blogPost['confluence_title'] ) ) {
				$title = (string)$blogPost['confluence_title'];
			}

			if ( $title !== '' ) {
				$titles[$pageId] = $title;
			}
		}

		$invalidTitles = false;

		$validityChecker = new TitleValidityChecker();

		foreach ( $titles as $pageId => $title ) {
			if ( !$validityChecker->hasValidEnding( $title ) ) {
				$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title ens with invalid character' );
			}
			if ( str_contains( $title, ':' ) ) {
				if ( $validityChecker->hasDoubleColon( $title ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains multiple collons' );
					$invalidTitles = true;
				}
				$namespace = substr( $title, 0, strpos( $title, ':' ) );
				$text = substr( $title, strpos( $title, ':' ) + 1 );

				if ( !$validityChecker->hasValidNamespace( $namespace ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Invalid namespace character detected' );
					$invalidTitles = true;
				}

				if ( !$validityChecker->hasValidLength( $text ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains to many characters (>256)' );
					$invalidTitles = true;
				}
			} else {
				if ( !$validityChecker->hasValidLength( $title ) ) {
					$this->workspaceDB->addInvalidTitle( $pageId, $title, 'Title contains to many characters (>256)' );
					$invalidTitles = true;
				}
			}
		}

		$invalidAttachments = false;
		$pageAttachments = $this->workspaceDB->getPageAttachments();
		foreach ( $pageAttachments as $attachment ) {
			$attachmentId = $attachment['attachment_id'];
			$wikiTitle = $attachment['target_attachment_filename'];
			if ( !$validityChecker->hasValidLength( $wikiTitle ) ) {
				$this->workspaceDB->addInvalidTitle(
					$attachmentId,
					$wikiTitle,
					'Attachment title contains to many characters (>256)'
				);
				$invalidAttachments = true;
			}
		}

		if ( !empty( $this->dbLog->getLogEntriesForStep( 'analyze' ) ) ) {
			$this->writeln( "\n\nWARNINGS / ERRORS:\n" );
			$this->writeln(
				"\nPlease check logging table in workspaceDB for details about invalid titles and filenames\n\n"
			);
		}

		if ( $invalidTitles ) {
			$this->writeln( "\n\INVALID PAGE TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( $invalidAttachments ) {
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
