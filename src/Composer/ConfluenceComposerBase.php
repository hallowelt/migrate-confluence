<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPostComments;
use HalloWelt\MigrateConfluence\Composer\Processor\BlogPosts;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultFiles;
use HalloWelt\MigrateConfluence\Composer\Processor\DefaultPages;
use HalloWelt\MigrateConfluence\Composer\Processor\Files;
use HalloWelt\MigrateConfluence\Composer\Processor\InvalidContents;
use HalloWelt\MigrateConfluence\Composer\Processor\PageComments;
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

abstract class ConfluenceComposerBase extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/** @var MigrationConfig */
	protected MigrationConfig $migrationConfig;

	/** @var string */
	protected string $dest = '';

	/** @var Workspace|null */
	protected $workspace = null;

	protected Output $output;

	protected DBComposerDataLookup $dataLookup;

	/** @var ComposerSkipHelper */
	protected ComposerSkipHelper $skipHelper;

	/** @var WorkspaceDB|null */
	protected ?WorkspaceDB $workspaceDB = null;

	/** @var DBLog|null */
	protected ?DBLog $dbLog = null;

	/** @var int Total number of parallel compose worker processes (1 = no parallelism) */
	protected int $workerCount = 1;

	/** @var int Zero-based index of this worker process among $workerCount */
	protected int $workerIndex = 0;

	/**
	 * @var bool Set on the single, non-parallel pass that runs after all compose workers have
	 * finished, to aggregate wiki-level artifacts (deployment.txt, wikiimport.sh, shared
	 * content, wiki-level sidebar) that cannot be safely produced by concurrent workers
	 * touching the same wiki. See WikiBasedComposer.
	 */
	protected bool $finalizeOnly = false;

	/**
	 * @var array<string,string[]> subDir (wikiName/namespace) => file extensions, collected
	 * from all workers by the orchestrator (via ComposeDataWriter over the fd-3 DB pipe) and
	 * passed through here for the finalize pass to consume. Empty outside a finalize pass.
	 * See WikiBasedComposer.
	 */
	protected array $namespaceFileExtensions = [];

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

		$this->workerCount = (int)( $config['worker-count'] ?? 1 );
		$this->workerIndex = (int)( $config['worker-index'] ?? 0 );
		$this->finalizeOnly = (bool)( $config['compose-finalize-only'] ?? false );
		$this->namespaceFileExtensions = $config['namespace-file-extensions'] ?? [];

		$this->workspace = $workspace;
	}

	/**
	 * Whether this instance is a spawned worker process (--workers > 1). Workers must not
	 * write to the shared DB log or aggregated log files; only the single-process run does.
	 *
	 * @return bool
	 */
	protected function isWorker(): bool {
		return $this->workerCount > 1 && !$this->finalizeOnly;
	}

	/**
	 * Whether this is the single, non-parallel finalize pass that runs after all compose
	 * workers have finished (see WikiBasedComposer for what it aggregates).
	 *
	 * @return bool
	 */
	protected function isFinalizeOnly(): bool {
		return $this->finalizeOnly;
	}

	/**
	 * Round-robin slice check: true if the item at $index belongs to this worker.
	 * Namespace/wiki sizes are not taken into account; distribution is a simple modulo split.
	 *
	 * @param int $index
	 * @return bool
	 */
	protected function isMyShare( int $index ): bool {
		if ( $this->workerCount <= 1 ) {
			return true;
		}
		return $index % $this->workerCount === $this->workerIndex;
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
		// Workers open the DB read-only: they never write to it, and concurrent
		// writers would be unsafe. Only the single (non-parallel) run writes the
		// version log entry.
		$this->workspaceDB = WorkspaceDB::open( $this->dest, $this->isWorker() );
		$this->dataLookup = new DBComposerDataLookup( $this->workspaceDB );
		$this->dbLog = new DBLog( $this->workspaceDB );
		if ( !$this->isWorker() ) {
			$this->logMigrateConfluenceToolVersion( $this->dbLog );
		}
		$this->skipHelper = new ComposerSkipHelper( $this->dataLookup, $this->migrationConfig );

		$this->doBuildXML( $builder );
	}

	abstract protected function doBuildXML( Builder $builder ): void;

	/**
	 * @param array $spaces
	 * @return array
	 */
	protected function buildSpacesMap( array $spaces ): array {
		$map = [];
		foreach ( $spaces as $space ) {
			$spaceId = (int)$space['space_id'];
			$namespace = empty( $space['namespace_prefix'] ) ? 'NS_MAIN' : $space['namespace_prefix'];

			if ( !isset( $map[$namespace] ) ) {
				$map[$namespace] = [];
			}
			$map[$namespace][$spaceId] = $space;
		}

		return $map;
	}

	/**
	 * @param Builder $builder
	 * @return array
	 */
	protected function initProcessorsForSharedContent(
		Builder $builder
	): array {
		return [
			new DefaultFiles(
				$this->dataLookup, $this->workspace, $this->output, $this->dest, $this->migrationConfig
			),
			new DefaultPages(
				$builder, $this->output, $this->dest, $this->migrationConfig, $this->dataLookup
			),
		];
	}

	/**
	 * @param Builder $builder
	 * @param string $subDir
	 * @param int[] $spaceIds
	 * @return void
	 */
	protected function runSharedContentProcessors( Builder $builder, string $subDir, array $spaceIds ): void {
		foreach ( $this->initProcessorsForSharedContent( $builder ) as $processor ) {
			$processor->setSubDir( $subDir );
			if ( $processor instanceof ISpaceIdsDependentProcessor ) {
				$processor->setCurrentSpaceIds( $spaceIds );
			}
			$processor->execute();
		}
	}

	/**
	 * @param Builder $builder
	 * @return array
	 */
	protected function initProcessorsForSpaceContent(
		Builder $builder, ComposerDeploymentInfo $deploymentInfo
	): array {
		return [
			new Files(
				$this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Pages(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new BlogPosts(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Templates(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new PageComments(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new BlogPostComments(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig,
				$deploymentInfo, $this->skipHelper
			),
			new Users(
				$this->dataLookup, $this->output, $this->dest
			),
			new InvalidContents(
				$builder, $this->dataLookup, $this->workspace,
				$this->output, $this->dest, $this->migrationConfig
			),
		];
	}

	/**
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $subDir
	 * @return void
	 */
	protected function writeDeploymentLog(
		ComposerDeploymentInfo $deploymentInfo, string $subDir
	): void {
		$content = "# Namespaces\n\n";
		$namespaces = $deploymentInfo->getNamespaces();
		$content .= $this->makeListContent( $namespaces );

		$content .= "\n\n# File extensions\n\n";
		$fileExtensions = $deploymentInfo->getFileExtensions();
		$content .= $this->makeListContent( $fileExtensions );

		$logDir = $this->ensureDeploymentInfoPath( $subDir );
		file_put_contents( $logDir . "/deployment.txt", $content );
	}

	/**
	 * @param string $namespace
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param string $subDir
	 * @return void
	 */
	protected function writeSkippedPagesLog(
		string $namespace, ComposerDeploymentInfo $deploymentInfo, string $subDir = ''
	): void {
		$skippedPages = $deploymentInfo->getSkippedPages();
		$content = $this->makeListContent( $skippedPages );

		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/skipped_pages.log", $content );
	}

	/**
	 * @param DBLog $dbLog
	 * @return void
	 */
	protected function writeUserReadableDBLog( DBLog $dbLog ): void {
		$this->writeDBLogContent( $dbLog, 'error' );
		$this->writeDBLogContent( $dbLog, 'warning' );
		$this->writeDBLogContent( $dbLog, 'info' );
	}

	/**
	 * @param array $data
	 * @return string
	 */
	protected function makeListContent( array $data ): string {
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
	protected function writeDBLogContent( DBLog $dbLog, string $type ): void {
		$data = $dbLog->getLogEntriesForStep( 'compose', $type );
		$content = '';
		foreach ( $data as $item ) {
			$content .= $item['caller'] . ': ' . $item['text'] . "\n";
		}
		file_put_contents( $this->dest . "/composer_{$type}.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidPagesLog( array $spaceIds, string $namespace = '', string $subDir = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidPages( (int)$spaceId ) );
		}
		$content = "page_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['page_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_pages.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidBlogPostsLog( array $spaceIds, string $namespace = '', string $subDir = '' ): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidBlogPosts( (int)$spaceId ) );
		}
		$content = "blog_post_id;space_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['blog_post_id'] . ';';
			$line .= $item['space_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_blog_posts.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidPageTemplatesLog(
		array $spaceIds, string $namespace = '', string $subDir = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidPageTemplates( (int)$spaceId ) );
		}
		$content = "template_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['template_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_page_templates.log", $content );
	}

	/**
	 * @param array $spaceIds
	 * @param string $namespace
	 * @param string $subDir
	 *
	 * @return void
	 */
	protected function writeInvalidAttachmentsLog(
		array $spaceIds, string $namespace = '', string $subDir = ''
	): void {
		$data = [];
		foreach ( $spaceIds as $spaceId ) {
			$data = array_merge( $data, $this->dataLookup->getInvalidAttachments( (int)$spaceId ) );
		}
		$content = "attachment_id;page_id;confluence_title;wiki_title;text\n";
		foreach ( $data as $item ) {
			$line = $item['attachment_id'] . ';';
			$line .= $item['page_id'] . ';';
			$line .= $item['confluence_title'] . ';';
			$line .= $item['wiki_title'] . ';';
			$line .= $item['text'] . ';';
			$content .= $line . "\n";
		}
		$logDir = $this->ensureLogPath( $subDir );
		file_put_contents( $logDir . "/invalid_attachments.log", $content );
	}

	/**
	 * @param string $subDir
	 * @return string
	 */
	protected function ensureLogPath( string $subDir ): string {
		$path = $this->dest . "/result";
		$path .= "/$subDir/log";
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * @param string $subDir
	 * @return string
	 */
	protected function ensureDeploymentInfoPath( string $subDir ): string {
		$path = $this->dest . "/result";
		$path .= "/$subDir";
		if ( !is_dir( $path ) ) {
			mkdir( $path, 0755, true );
		}

		return $path;
	}

	/**
	 * Add version information of the migrate confluence tool to the database
	 *
	 * @param DBLog $dbLog
	 * @return void
	 */
	protected function logMigrateConfluenceToolVersion( DBLog $dbLog ): void {
		$dbLog->addLogEntry(
			'info',
			'compose',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @param string $subDir
	 * @return void
	 */
	protected function addSpaceImportHelper( string $subDir = '' ): void {
		$sourcePaths = glob( __DIR__ . '/_shell/*' );
		if ( $sourcePaths === false || $sourcePaths === [] ) {
			return;
		}

		if ( $subDir !== '' ) {
			$sourcePath = __DIR__ . '/_shell/spaceimport.sh';
			$targetDir = $this->dest . "/result/$subDir";
			$this->copyShellScript( $sourcePath, $targetDir . '/spaceimport.sh' );
		}
	}

	/**
	 * @param string $sourcePath
	 * @param string $targetPath
	 * @return void
	 */
	protected function copyShellScript( string $sourcePath, string $targetPath ): void {
		if ( !file_exists( $sourcePath ) ) {
			throw new \RuntimeException( 'Could not find shell script: ' . $sourcePath );
		}

		if ( !copy( $sourcePath, $targetPath ) ) {
			throw new \RuntimeException( 'Failed to copy shell script: ' . $sourcePath );
		}

		$sourcePerms = fileperms( $sourcePath );
		if ( $sourcePerms !== false ) {
			chmod( $targetPath, $sourcePerms & 0777 );
		}
	}
}
