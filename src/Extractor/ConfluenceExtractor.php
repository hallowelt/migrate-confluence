<?php

namespace HalloWelt\MigrateConfluence\Extractor;

use HalloWelt\MediaWiki\Lib\Migration\ExtractorBase;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReaderAware;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriterAware;
use HalloWelt\MigrateConfluence\Extractor\Preprocessor\PopulateAdditionalAttachmentsTable;
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
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostComments;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractBlogPostsMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractCommentsBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageComments;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesBodyContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPagesMetaData;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractPageTemplateContents;
use HalloWelt\MigrateConfluence\Extractor\Processor\ExtractSpaceDescriptionBodyContents;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWikisConfigAware;
use HalloWelt\MigrateConfluence\IWorkspaceAware;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

class ConfluenceExtractor extends ExtractorBase implements
	IWorkspaceAware,
	IOutputAwareInterface,
	IMigrationConfigAware,
	IWikisConfigAware,
	IExtractorDataReaderAware,
	IExtractorDataWriterAware
{

	/** @var Workspace|null */
	private ?Workspace $workspace = null;

	/** @var Output|null */
	private ?Output $output = null;

	/** @var IExtractorDataWriter|null */
	private ?IExtractorDataWriter $writer = null;

	/** @var IExtractorDataReader|null */
	private ?IExtractorDataReader $reader = null;

	/** @var MigrationConfig|null */
	private ?MigrationConfig $migrationConfig = null;

	/** @var DBLog */
	private DBLog $dbLog;

	/** @var WikisConfig|null */
	private ?WikisConfig $wikisConfig = null;

	public function setWorkspace( Workspace $workspace ): void {
		$this->workspace = $workspace;
	}

	/**
	 * @param OutputInterface $output
	 * @return void
	 */
	public function setOutput( OutputInterface $output ): void {
		$this->output = $output;
	}

	/**
	 * @param IExtractorDataWriter $writer
	 * @return void
	 */
	public function setDataWriter( IExtractorDataWriter $writer ): void {
		$this->writer = $writer;
	}

	/**
	 * @param IExtractorDataReader $reader
	 * @return void
	 */
	public function setDataReader( IExtractorDataReader $reader ): void {
		$this->reader = $reader;
	}

	/**
	 * @param MigrationConfig $migrationConfig
	 * @return void
	 */
	public function setMigrationConfig( MigrationConfig $migrationConfig ): void {
		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @param WikisConfig $wikisConfig
	 * @return void
	 */
	public function setWikisConfig( WikisConfig $wikisConfig ): void {
		$this->wikisConfig = $wikisConfig;
	}

	/**
	 * @return void
	 */
	private function initDBLog(): void {
		$this->dbLog = new DBLog( $this->writer );
		$this->dbLog->addLogEntry(
			'info',
			'extract',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @param SplFileInfo $file
	 * @return bool
	 */
	public function extract( SplFileInfo $file ): bool {
		if ( $this->workspace === null ) {
			throw new RuntimeException( "Workspace is not set" );
		}
		if ( $this->reader === null ) {
			throw new RuntimeException( "Data reader is not set" );
		}
		if ( $this->writer === null ) {
			throw new RuntimeException( "Data writer is not set" );
		}
		if ( $this->migrationConfig === null ) {
			throw new RuntimeException( "Migration config is not set" );
		}
		if ( $this->wikisConfig === null ) {
			throw new RuntimeException( "Wikis config is not set" );
		}
		if ( $this->output === null ) {
			throw new RuntimeException( "Output is not set" );
		}
		if ( !$file->isFile() ) {
			throw new RuntimeException( "File does not exist: {$file->getPathname()}" );
		}
		if ( !$file->isReadable() ) {
			throw new RuntimeException( "File is not readable: {$file->getPathname()}" );
		}

		$this->initDBLog();

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
			new UpdateBodyContentIdsFallback( $this->reader, $this->dbLog, $this->writer ),
			new UpdatePagesTableWithSpaceIdOfHistoryVersions( $this->reader, $this->dbLog, $this->writer ),
			new UpdatePagesTableWithWikiTitle(
				$this->reader, $this->dbLog, $this->writer, $this->migrationConfig, $this->wikisConfig ),
			new UpdateBlogPostsTableWithSpaceIdOfHistoryVersions( $this->reader, $this->dbLog, $this->writer ),
			new UpdateBlogPostsTableWithWikiTitle( $this->reader, $this->dbLog, $this->writer ),
			new UpdatePageTemplatesWithWikiTitle( $this->reader, $this->dbLog, $this->writer ),
			new UpdatePageAttachmentTable( $this->reader, $this->dbLog, $this->writer, $this->migrationConfig ),
			new UpdateBlogPostAttachmentTable( $this->reader, $this->dbLog, $this->writer, $this->migrationConfig ),
			new PopulateAdditionalAttachmentsTable(
				$this->reader, $this->dbLog, $this->writer, $this->migrationConfig
			),
		];
	}

	/**
	 * @return array
	 */
	private function getProcessors(): array {
		return [
			new ExtractSpaceDescriptionBodyContents( $this->reader, $this->workspace, $this->dbLog, $this->writer ),
			new ExtractPagesBodyContents( $this->reader, $this->workspace, $this->dbLog, $this->writer ),
			new ExtractBlogPostsBodyContents( $this->reader, $this->workspace, $this->dbLog, $this->writer ),
			new ExtractCommentsBodyContents( $this->reader, $this->workspace, $this->dbLog, $this->writer ),
			new ExtractPageTemplateContents( $this->reader, $this->workspace, $this->dbLog, $this->writer ),
			new ExtractPagesMetaData( $this->reader, $this->dbLog, $this->writer, $this->migrationConfig ),
			new ExtractBlogPostsMetaData( $this->reader, $this->dbLog, $this->writer, $this->migrationConfig ),
			new ExtractAttachmentsMetaData( $this->reader, $this->dbLog, $this->writer, $this->migrationConfig ),
			new BuildAttachmentDescriptions( $this->reader, $this->dbLog, $this->writer ),
			new ExtractPageComments( $this->reader, $this->dbLog, $this->writer ),
			new ExtractBlogPostComments( $this->reader, $this->dbLog, $this->writer ),
		];
	}

	/**
	 * @return void
	 */
	private function checkTitles(): void {
		if ( !empty( $this->reader->getLogEntriesForStep( 'analyze' ) ) ) {
			$this->writeln( "\n\nWARNINGS / ERRORS:\n" );
			$this->writeln(
				"\nPlease check logging table in workspaceDB for details about invalid titles and filenames\n\n"
			);
		}

		if ( !empty( $this->reader->getInvalidPageWikiTitles() ) ) {
			$this->writeln( "\n\INVALID PAGE TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check page_invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( !empty( $this->reader->getInvalidBlogPostWikiTitles() ) ) {
			$this->writeln( "\n\INVALID BLOG POST TITLES DETECTED:\n" );
			$this->writeln(
				"\nPlease check blog_post_invalid_titles table in workspaceDB for details\n\n"
			);
		}

		if ( !empty( $this->reader->getInvalidAttachmentTitles() ) ) {
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
