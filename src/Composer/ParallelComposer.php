<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\Migration\ComposerBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;

/**
 * Handles parallel processing orchestration for Confluence migrations.
 * Manages worker coordination, namespace sharding, and pipe-based communication
 * between worker processes.
 */
abstract class ParallelComposer extends ComposerBase {

	/**
	 * Round-robin index into the flattened list of (wiki, namespace) pairs across *all*
	 * wikis, shared across every call to storeMigrationResult() in this instance. This is
	 * what gives worker sharding namespace-level granularity instead of only wiki-level
	 * granularity: a single large wiki with many namespaces still gets spread across every
	 * worker, not pinned to just one of them.
	 *
	 * @var int
	 */
	protected int $namespaceShardIndex = 0;

	/** @var PipeChannel|null Lazily opened; only used when isWorker() actually sends data. */
	protected ?PipeChannel $pipeChannel = null;

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
	private bool $isInFinalizePass = false;

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

		$this->workerCount = (int)( $config['worker-count'] ?? 1 );
		$this->workerIndex = (int)( $config['worker-index'] ?? 0 );
		$this->isInFinalizePass = (bool)( $config['compose-finalize-only'] ?? false );
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
		return $this->workerCount > 1 && !$this->isFinalStep();
	}

	/**
	 * Whether this is the single, non-parallel finalize pass that runs after all compose
	 * workers have finished (see finalizeWikis() for what it aggregates).
	 *
	 * @return bool
	 */
	protected function isFinalStep(): bool {
		return $this->isInFinalizePass;
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
	 * @return PipeChannel
	 */
	protected function getPipeChannel(): PipeChannel {
		if ( $this->pipeChannel === null ) {
			$this->pipeChannel = new PipeChannel();
		}
		return $this->pipeChannel;
	}
}
