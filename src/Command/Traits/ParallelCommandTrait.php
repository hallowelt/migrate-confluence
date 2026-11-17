<?php

namespace HalloWelt\MigrateConfluence\Command\Traits;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use Symfony\Component\Console\Input\InputOption;

/**
 * Shared trait for commands that support parallel worker processing.
 * Provides orchestration for spawning worker processes and aggregating results.
 *
 * @property ExecutionTime|null $executionTime Tracks total execution time across orchestrator and workers
 * @property DataBuckets|null $executionTimeBuckets Buckets for aggregating execution time metrics
 * @property Workspace|null $workspace Workspace instance from parent command
 */
trait ParallelCommandTrait {

	/**
	 * Configure parallel processing options (--workers and --worker).
	 * Call this from configure() in commands that support parallelism.
	 *
	 * @return void
	 */
	protected function configureParallelOptions(): void {
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'workers',
				null,
				InputOption::VALUE_REQUIRED,
				'Number of parallel worker processes to spawn (default: 1, no parallelism)',
				1
			)
		);
		// Hidden internal option — set automatically by the orchestrator on each child process.
		$definition->addOption(
			new InputOption(
				'worker',
				null,
				InputOption::VALUE_REQUIRED,
				'[Internal] Zero-based index of this worker process'
			)
		);
	}

	/**
	 * Whether this process is a spawned worker (--worker option set).
	 *
	 * @return bool
	 */
	protected function isWorkerProcess(): bool {
		return $this->input->hasParameterOption( '--worker' );
	}

	/**
	 * Preserve execution time across multiple execution phases.
	 * When an orchestrator spawns workers, we want to track the total time, not reset
	 * it when a phase completes. Call this in overridden initExecutionTime() methods
	 * to preserve a previous phase's timer.
	 *
	 * @return void
	 */
	protected function preserveExecutionTime(): void {
		if ( $this->executionTime !== null ) {
			$this->executionTimeBuckets = new DataBuckets( [ 'execution-time' ] );
			$this->executionTimeBuckets->loadFromWorkspace( $this->workspace );
		}
	}

	/**
	 * Skip logging execution time for worker processes (they don't have complete timing).
	 * Call this from overridden logExecutionTime() methods to gate logging to orchestrator only.
	 *
	 * @return bool True if this is a worker process or finalize-only phase (don't log)
	 */
	protected function shouldSkipExecutionTimeLogging(): bool {
		return $this->isWorkerProcess();
	}
}
