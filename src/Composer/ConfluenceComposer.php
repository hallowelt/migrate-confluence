<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPosts;
use HalloWelt\MigrateConfluence\Composer\Processor\Comments;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultFiles;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Composer\Processor\Pages;
use HalloWelt\MigrateConfluence\Composer\Processor\Templates;
use HalloWelt\MigrateConfluence\Composer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Output\Output;

class ConfluenceComposer extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/**
	 * @var Output|null
	 */
	private ?Output $output = null;

	/** @var MigrationConfig */
	private MigrationConfig $migrationConfig;

	/** @var string */
	private string $dest = '';

	private DBComposerDataLookup $dataLookup;

	/**
	 * @param array $config
	 * @param Workspace $workspace
	 * @param DataBuckets $buckets
	 */
	public function __construct( $config, Workspace $workspace, DataBuckets $buckets ) {
		parent::__construct( $config, $workspace, $buckets );

		if ( isset( $config['config'] ) ) {
			$this->migrationConfig = new MigrationConfig( $config['config'] );
		} else {
			$this->migrationConfig = new MigrationConfig( [] );
		}
	}

	/**
	 * @param Output $output
	 */
	public function setOutput( Output $output ): void {
		$this->output = $output;
	}

	/**
	 * @inheritDoc
	 */
	public function setDestinationPath( string $dest ): void {
		$this->dest = $dest;
	}

	/**
	 * @param Builder $builder
	 * @return void
	 */
	public function buildXML( Builder $builder ): void {
		$workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
		$dbLog = new DBLog( $workspaceDB );
		$this->logMigrateConfluenceToolVersion( $dbLog );

		$this->dataLookup = new DBComposerDataLookup( $workspaceDB );
		$deploymentInfo = new ComposerDeploymentInfo();
		$skipHelper = new ComposerSkipHelper( $this->dataLookup, $this->migrationConfig );
		$processors = [
			new DefaultFiles(
				$this->dataLookup, $this->workspace, $this->output, $this->dest, $this->migrationConfig
			),
			new DefaultPages(
				$builder, $this->output, $this->dest, $this->migrationConfig
			),
			new Files(
				$this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Pages(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new BlogPosts(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Templates(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Comments(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $skipHelper
			),
			new Users(
				$this->dataLookup, $this->output, $this->dest
			),
		];

		// Run space dependent processors for each space
		$spaces = $this->dataLookup->getSpaces();
		foreach ( $spaces as $space ) {
			$spaceId = $space['space_id'];
			$spaceKey = $space['space_key'];
			$namespace = 'NS_MAIN';
			if ( str_contains( $space['space_prefix'], ':' ) ) {
				$namespace = substr( $space['space_prefix'], 0, strpos( $space['space_prefix'], ':' ) );
			}

			if ( $skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip space '$spaceKey' by configuration." );
				continue;
			}
			$deploymentInfo->addNamespace( $namespace );

			foreach ( $processors as $processor ) {
				if ( $processor instanceof ISpaceDependentProcessor ) {
					$processor->setCurrentSpaceId( $spaceId );
				}
				$processor->setSubDir( $namespace );
				$processor->execute();
			}
		}

		$this->writeDeploymentLog( $deploymentInfo );
		$this->writeSkippedPagesLog( $deploymentInfo );
		$this->writeUserReadableDBLog( $dbLog );
		$this->writeInvalidPagesLog();
		$this->writeInvalidBlogPostsLog();
		$this->writeInvalidAttachmentsLog();
		$this->writeInvalidPageTemplatesLog();
	}

	/**
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @return void
	 */
	private function writeDeploymentLog( ComposerDeploymentInfo $deploymentInfo ): void {
		$content = "# Namespaces\n\n";
		$namespaces = $deploymentInfo->getNamespaces();
		$content .= $this->makeListContent( $namespaces );

		$content .= "\n\n# File extensions\n\n";
		$fileExtensions = $deploymentInfo->getFileExtensions();
		$content .= $this->makeListContent( $fileExtensions );

		file_put_contents( $this->dest . '/deployment.log', $content );
	}

	/**
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @return void
	 */
	private function writeSkippedPagesLog( ComposerDeploymentInfo $deploymentInfo ): void {
		$skippedPages = $deploymentInfo->getSkippedPages();
		$content = $this->makeListContent( $skippedPages );

		file_put_contents( $this->dest . '/skipped_pages.log', $content );
	}

	/**
	 * @param DBLog $dbLog
	 * @return void
	 */
	private function writeUserReadableDBLog( DBLog $dbLog ): void {
		$this->writeDBLogContent( $dbLog, 'error' );
		$this->writeDBLogContent( $dbLog, 'warning' );
		$this->writeDBLogContent( $dbLog, 'info' );
	}

	/**
	 * @param array $data
	 * @return string
	 */
	private function makeListContent( array $data ): string {
		$content = '';
		foreach ( $data as $item ) {
			$content .= "$item\n";
		}
		return $content;
	}

	/**
	 * @param DBLog $dbLog
	 * @param string $type
	 * @return void
	 */
	private function writeDBLogContent( DBLog $dbLog, string $type ): void {
		$data = $dbLog->getLogEntriesForStep( 'compose', $type );
		$content = '';
		foreach ( $data as $item ) {
			$content .= $item['caller'] . ': ' . $item['text'] . "\n";
		}
		file_put_contents( $this->dest . "/composer_{$type}.log", $content );
	}

	/**
	 * @return void
	 */
	private function writeInvalidPagesLog(): void {
		$data = $this->dataLookup->getInvalidPages();
		$content = "page_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['page_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		file_put_contents( $this->dest . "/invalid_pages.log", $content );
	}

	/**
	 * @return void
	 */
	private function writeInvalidBlogPostsLog(): void {
		$data = $this->dataLookup->getInvalidBlogPosts();
		$content = "blog_post_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['blog_post_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		file_put_contents( $this->dest . "/invalid_blog_posts.log", $content );
	}

	/**
	 * @return void
	 */
	private function writeInvalidPageTemplatesLog(): void {
		$data = $this->dataLookup->getInvalidPageTemplates();
		$content = "template_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['template_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		file_put_contents( $this->dest . "/invalid_page_templates.log", $content );
	}

	/**
	 * @return void
	 */
	private function writeInvalidAttachmentsLog(): void {
		$data = $this->dataLookup->getInvalidAttachments();
		$content = "attachment_id;page_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['attachment_id'] . ';';
			$line .= $item['page_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		file_put_contents( $this->dest . "/invalid_attachments.log", $content );
	}

	/**
	 * Add version information of the migrate confluece tool to the database
	 *
	 * @param DBLog $dbLog
	 * @return void
	 */
	private function logMigrateConfluenceToolVersion( DBLog $dbLog ): void {
		$dbLog->addLogEntry(
			'info',
			'compose',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}
}
