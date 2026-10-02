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
use HalloWelt\MigrateConfluence\Composer\Processor\Sidebar;
use HalloWelt\MigrateConfluence\Composer\Processor\Templates;
use HalloWelt\MigrateConfluence\Composer\Processor\Users;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Output\Output;

/**
 * All Confluence migrations are composed on a per-wiki basis: one output directory per
 * target wiki below workspace/result, each containing namespace directories and a
 * wiki-local _shared directory for wiki-scoped defaults. If no wiki mapping is configured
 * (no --wikis CSV supplied to analyze), all spaces are grouped under a single implicit
 * wiki, see DEFAULT_WIKI_NAME.
 */
class ConfluenceComposerBase extends ComposerBase implements IOutputAwareInterface, IDestinationPathAware {

	/**
	 * Directory/name used for the single implicit wiki when no wiki mapping was configured
	 * (no --wikis CSV supplied to analyze). In that case all spaces are treated as if they
	 * belonged to this one wiki.
	 */
	private const DEFAULT_WIKI_NAME = 'default';

	/**
	 * Round-robin index into the flattened list of (wiki, namespace) pairs across *all*
	 * wikis, shared across every call to storeMigrationResult() in this instance. This is
	 * what gives worker sharding namespace-level granularity instead of only wiki-level
	 * granularity: a single large wiki with many namespaces still gets spread across every
	 * worker, not pinned to just one of them.
	 *
	 * @var int
	 */
	private int $namespaceShardIndex = 0;

	/** @var PipeChannel|null Lazily opened; only used when isWorker() actually sends data. */
	private ?PipeChannel $pipeChannel = null;

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
	 * touching the same wiki. See finalizeWikis().
	 */
	protected bool $finalizeOnly = false;

	/**
	 * @var array<string,string[]> subDir (wikiName/namespace) => file extensions, collected
	 * from all workers by the orchestrator (via ComposeDataWriter over the fd-3 DB pipe) and
	 * passed through here for the finalize pass to consume. Empty outside a finalize pass.
	 * See finalizeWikis().
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
	 * workers have finished (see finalizeWikis() for what it aggregates).
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

	/**
	 * @param Builder $builder
	 * @return void
	 */
	protected function doBuildXML( Builder $builder ): void {
		$wikiNames = $this->getConfiguredWikiNames();

		if ( $this->isFinalizeOnly() ) {
			// Workers only produced per-namespace artifacts (self-contained). Aggregate the
			// once-per-wiki artifacts here, in a single non-parallel pass, without redoing
			// any of the actual (expensive) content processing.
			$this->finalizeWikis( $wikiNames, $builder );
			$this->writeUserReadableDBLog( $this->dbLog );
			return;
		}

		// Run space dependent processors for each space, grouped by target wiki.
		$this->output->writeln( "Data is assigned to some wikis." );

		$fileExtensionsPerWiki = [];
		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->getSpacesForWiki( $wikiName );
			if ( $spaces === [] ) {
				$this->output->writeln( "No spaces found for wiki '$wikiName'." );
				continue;
			}

			// Shared content and the wiki-level sidebar/deployment.txt/wikiimport.sh are
			// once-per-wiki artifacts. If multiple workers process namespaces of the *same*
			// wiki concurrently, only one of them (the single, non-parallel run) may write
			// these; otherwise workers would race on the same files or overwrite each other's
			// (partial) ComposerDeploymentInfo. They are produced later by the finalize pass
			// instead — see isFinalizeOnly() above.
			if ( !$this->isWorker() ) {
				$this->runSharedContentProcessors(
					$builder,
					$wikiName . '/_shared',
					array_map( 'intval', array_column( $spaces, 'space_id' ) )
				);
			}

			$spacesMap = $this->buildSpacesMap( $spaces );
			$deploymentInfo = $this->storeMigrationResult( $spacesMap, $builder, $wikiName );

			if ( !$this->isWorker() ) {
				$sidebarProcessor = new Sidebar(
					$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
				);
				$sidebarProcessor->setSubDir( $wikiName );
				$sidebarProcessor->execute();

				$this->addWikiImportHelper( $wikiName );
				$this->writeDeploymentLog( $deploymentInfo, $wikiName );
			}

			$fileExtensionsPerWiki[$wikiName] = $deploymentInfo->getFileExtensions();
			$this->output->writeln( "Processing wiki '$wikiName' with " . count( $spaces ) . " spaces." );
		}

		if ( !$this->isWorker() ) {
			$this->writeUserReadableDBLog( $this->dbLog );
			$this->writeManifest( $fileExtensionsPerWiki );
		}
	}

	/**
	 * Target wiki names to process. If no wiki mapping was configured (no --wikis CSV
	 * supplied to analyze), all spaces are grouped under a single implicit wiki name, so
	 * migrations without an explicit wiki mapping are composed the same way as migrations
	 * with exactly one configured wiki.
	 *
	 * @return string[]
	 */
	protected function getConfiguredWikiNames(): array {
		$wikiNames = $this->dataLookup->getWikisConfigWikiNames();
		if ( $wikiNames === [] ) {
			return [ self::DEFAULT_WIKI_NAME ];
		}
		return $wikiNames;
	}

	/**
	 * @param string $wikiName
	 * @return array
	 */
	protected function getSpacesForWiki( string $wikiName ): array {
		if ( $this->dataLookup->getWikisConfigWikiNames() === [] ) {
			// No wiki mapping configured: every known space belongs to the implicit wiki.
			return $this->dataLookup->getSpaces();
		}
		return $this->dataLookup->getWikisConfigSpacesForWikiName( $wikiName );
	}

	/**
	 * Run the per-namespace processors for this worker's assigned slice of $spacesMap.
	 * Namespaces are round-robin sliced across worker processes (namespace-level, not
	 * wiki-level, granularity); namespace size is not taken into account.
	 *
	 * @param array $spacesMap
	 * @param Builder $builder
	 * @param string $wikiName
	 * @return ComposerDeploymentInfo Aggregated over the namespaces *this call* processed
	 *   (all of them for a single-process run, only this worker's slice otherwise).
	 */
	private function storeMigrationResult(
		array $spacesMap, Builder $builder, string $wikiName = ''
	): ComposerDeploymentInfo {
		$wikiDeploymentInfo = new ComposerDeploymentInfo();

		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				$this->output->writeln( "Skip namespace '$namespace' by configuration." );
				continue;
			}

			if ( !$this->isMyShare( $this->namespaceShardIndex++ ) ) {
				continue;
			}

			if ( $wikiName === '' ) {
				$this->output->writeln( "Wikiname for namespace '$namespace' is empty. -> skipping" );
				continue;
			}
			$subDir = $wikiName . '/' . $namespace;

			// Own instance per namespace: keeps this namespace's processing (and its
			// skipped-pages log) self-contained and safe to run concurrently with other
			// workers processing other namespaces of the same wiki.
			$namespaceDeploymentInfo = new ComposerDeploymentInfo();
			$namespaceDeploymentInfo->addNamespace( $namespace );

			$processors = $this->initProcessorsForSpaceContent( $builder, $namespaceDeploymentInfo );

			$spaceIds = array_keys( $spaces );
			foreach ( $processors as $processor ) {
				if ( $processor instanceof ISubDirAware ) {
					$processor->setSubDir( $subDir );
				}
				if ( $processor instanceof ISpaceIdsDependentProcessor ) {
					$processor->setCurrentSpaceIds( $spaceIds );
				}
				$processor->execute();
			}

			// Add enhanced sidebar to the namespace directory, not shared. It is a namespace-scoped feature.
			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			if ( $sidebarProcessor instanceof ISubDirAware ) {
				$sidebarProcessor->setSubDir( $subDir );
			}
			$sidebarProcessor->execute();

			$this->writeSkippedPagesLog( $namespace, $namespaceDeploymentInfo, $subDir );
			$this->writeInvalidPagesLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidBlogPostsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidAttachmentsLog( $spaceIds, $namespace, $subDir );
			$this->writeInvalidPageTemplatesLog( $spaceIds, $namespace, $subDir );

			$this->addSpaceImportHelper( $subDir );

			$wikiDeploymentInfo->addNamespace( $namespace );
			foreach ( $namespaceDeploymentInfo->getFileExtensions() as $extension ) {
				$wikiDeploymentInfo->addFileExtension( $extension );
			}

			if ( $this->isWorker() ) {
				// The wiki-level deployment.txt is deferred to the finalize pass (it may need
				// to merge contributions from other workers touching the same wiki). Send
				// this namespace's file extensions over the DB pipe (fd 3) so the orchestrator
				// can hand them to the finalize pass in memory — no temp files, mirroring how
				// Analyze/Convert stream results back to the parent process.
				$this->getPipeChannel()->send(
					[ 'addNamespaceExtensions', $subDir, $namespaceDeploymentInfo->getFileExtensions() ]
				);
			}
		}

		return $wikiDeploymentInfo;
	}

	/**
	 * @return PipeChannel
	 */
	private function getPipeChannel(): PipeChannel {
		if ( $this->pipeChannel === null ) {
			$this->pipeChannel = new PipeChannel();
		}
		return $this->pipeChannel;
	}

	/**
	 * Non-parallel pass run once after all compose workers have finished. Produces the
	 * once-per-wiki artifacts (shared content, wiki-level sidebar, deployment.txt,
	 * wikiimport.sh) that workers skipped while processing their namespace slices.
	 *
	 * @param array $wikiNames
	 * @param Builder $builder
	 * @return void
	 */
	private function finalizeWikis( array $wikiNames, Builder $builder ): void {
		foreach ( $wikiNames as $wikiName ) {
			$spaces = $this->getSpacesForWiki( $wikiName );
			if ( $spaces === [] ) {
				continue;
			}

			$this->runSharedContentProcessors(
				$builder,
				$wikiName . '/_shared',
				array_map( 'intval', array_column( $spaces, 'space_id' ) )
			);

			$spacesMap = $this->buildSpacesMap( $spaces );
			$deploymentInfo = $this->mergeWikiDeploymentInfo( $spacesMap, $wikiName );

			$sidebarProcessor = new Sidebar(
				$this->dataLookup, $this->migrationConfig, $this->dest, $spaces
			);
			$sidebarProcessor->setSubDir( $wikiName );
			$sidebarProcessor->execute();

			$this->addWikiImportHelper( $wikiName );
			$this->writeDeploymentLog( $deploymentInfo, $wikiName );
		}
	}

	/**
	 * Reconstruct the wiki-level ComposerDeploymentInfo from each namespace's file
	 * extensions, collected in memory from workers (via ComposeDataWriter) and passed
	 * through $this->namespaceFileExtensions, without re-running any content processing.
	 *
	 * @param array $spacesMap
	 * @param string $wikiName
	 * @return ComposerDeploymentInfo
	 */
	private function mergeWikiDeploymentInfo( array $spacesMap, string $wikiName ): ComposerDeploymentInfo {
		$deploymentInfo = new ComposerDeploymentInfo();

		foreach ( $spacesMap as $namespace => $spaces ) {
			if ( $this->skipHelper->skipNamespaceByConfiguration( $namespace ) ) {
				continue;
			}
			$deploymentInfo->addNamespace( $namespace );

			$subDir = $wikiName . '/' . $namespace;
			foreach ( $this->namespaceFileExtensions[$subDir] ?? [] as $extension ) {
				$deploymentInfo->addFileExtension( $extension );
			}
		}

		return $deploymentInfo;
	}

	/**
	 * @param string $subDir
	 * @return void
	 */
	protected function addWikiImportHelper( string $subDir = '' ): void {
		$sourcePaths = glob( __DIR__ . '/_shell/*' );
		if ( $sourcePaths === false || $sourcePaths === [] ) {
			return;
		}

		$targetDir = $this->dest . "/result";
		if ( $subDir !== '' ) {
			$targetDir .= "/$subDir";
			$sourcePath = __DIR__ . '/_shell/wikiimport.sh';
			$this->copyShellScript( $sourcePath, $targetDir . '/wikiimport.sh' );
		}
	}

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

	/**
	 * Writes a "manifest.json" file to the result directory, describing the deployable
	 * wikis produced by a multi-wiki (wiki-based) migration run.
	 *
	 * @param array $fileExtensionsPerWiki Map of wiki name to the file extensions used in that wiki
	 * @return void
	 */
	private function writeManifest( array $fileExtensionsPerWiki ): void {
		if ( $fileExtensionsPerWiki === [] ) {
			return;
		}

		$wikis = [];
		foreach ( $fileExtensionsPerWiki as $wikiName => $fileExtensions ) {
			$scripts = [];
			foreach ( $this->getManifestScriptTemplates() as $scriptTemplate ) {
				$scripts[] = str_replace( '<instance_id>', $wikiName, $scriptTemplate );
			}

			$wikis[] = [
				'sfr' => $wikiName,
				'file_extensions' => $fileExtensions,
				'scripts' => $scripts,
			];
		}

		$manifest = [
			'source_system' => 'confluence',
			'package_id' => $this->makePackageId(),
			'target' => [
				'wikis' => $wikis,
			],
		];

		file_put_contents(
			$this->dest . '/result/manifest.json',
			json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
		);
	}

	/**
	 * @return string[]
	 */
	private function getManifestScriptTemplates(): array {
		return [
			'./result/<instance_id>/wikiimport.sh --sfr=<instance_id> --add-defaults',
			'php /app/bluespice/w/maintenance/rebuildall.php --sfr=<instance_id>',
		];
	}

	/**
	 * Builds a package id from the name of the parent directory of the workspace
	 * (the migration project directory) and the current date, e.g.
	 * "customer-x-2026-08-10-v1". Falls back to "migration" if the parent
	 * directory name cannot be determined.
	 *
	 * @return string
	 */
	private function makePackageId(): string {
		$parentDirName = basename( dirname( rtrim( $this->dest, '/' ) ) );
		if ( $parentDirName === '' || $parentDirName === '.' || $parentDirName === '/' || $parentDirName === false ) {
			$parentDirName = 'migration';
		}

		return $parentDirName . '-' . date( 'Y-m-d' );
	}
}
