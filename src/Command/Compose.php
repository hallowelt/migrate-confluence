<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\DataWriter\ComposeDataWriter;
use HalloWelt\MigrateConfluence\Database\DataWriter\WorkerPool;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Compose extends CommandCompose {

	/**
	 * @var bool Set while running the single, non-parallel finalize pass that aggregates
	 * once-per-wiki artifacts after all compose workers have finished (see WikiBasedComposer).
	 */
	private bool $finalizeOnly = false;

	/**
	 * @var array<string,string[]> subDir (wikiName/namespace) => file extensions, collected
	 * in memory from all workers via ComposeDataWriter, for the finalize pass to consume.
	 */
	private array $namespaceFileExtensions = [];

	/**
	 * @inheritDoc
	 */
	protected function configure(): void {
		parent::configure();
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the config yaml file'
			)
		);
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
	 * @param array $config
	 *
	 * @return Compose
	 */
	public static function factory( array $config ): Compose {
		return new static( $config );
	}

	/**
	 * Intercept execution: when --workers > 1 and this is not already a spawned worker,
	 * act as the orchestrator and launch child processes. Namespaces/wikis are round-robin
	 * sliced across workers, regardless of size, and only the single (non-parallel) run
	 * writes to the compose DB log — workers omit it for simplicity.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$this->input = $input;
		$this->output = $output;

		$workers = (int)$input->getOption( 'workers' );
		$isChildProcess = $input->hasParameterOption( '--worker' );

		if ( !$isChildProcess && $workers > 1 ) {
			$this->dest = realpath( $input->getOption( 'dest' ) );
			if ( !is_dir( $this->dest ) ) {
				$output->writeln( "Destination does not exist" );
				return Command::FAILURE;
			}

			$writer = new ComposeDataWriter();
			$pool = new WorkerPool( $output, $writer );
			// Total duration should cover workers + finalize pass, not just the (short)
			// finalize pass on its own — see logExecutionTime()/initExecutionTime() overrides.
			$this->executionTime = new ExecutionTime();
			$result = $pool->run( WorkerPool::baseCommandFromArgv(), $workers );
			if ( $result !== Command::SUCCESS ) {
				return $result;
			}
			// All namespace-extension messages have been replayed into $writer by now,
			// since WorkerPool::run() only returns once every worker's fd-3 pipe hit EOF.
			$this->namespaceFileExtensions = $writer->getCollected();

			// Workers only produced per-namespace artifacts. Run a single, non-parallel
			// finalize pass in-process to aggregate the once-per-wiki artifacts
			// (deployment.txt, wikiimport.sh, shared content, wiki-level sidebar) and the
			// version-log DB write.
			$this->finalizeOnly = true;
			$result = parent::execute( $input, $output );
			$this->finalizeOnly = false;

			// Log the total (workers + finalize) execution time once, now that finalize's
			// own processFiles() run has set up $this->workspace/executionTimeBuckets.
			$this->logExecutionTime();

			return $result;
		}

		return parent::execute( $input, $output );
	}

	/**
	 * Keep the timer started before spawning workers (see execute()) alive across the
	 * finalize pass's processFiles() call, instead of resetting it to the finalize pass's
	 * own (short) runtime. executionTimeBuckets still needs to be (re)loaded here since
	 * finalize sets up a fresh Workspace instance.
	 *
	 * @return void
	 */
	protected function initExecutionTime(): void {
		if ( $this->finalizeOnly && $this->executionTime !== null ) {
			$this->executionTimeBuckets = new DataBuckets( [ 'execution-time' ] );
			$this->executionTimeBuckets->loadFromWorkspace( $this->workspace );
			return;
		}
		parent::initExecutionTime();
	}

	/**
	 * Workers must not log execution time at all (each would only know its own partial
	 * slice); the finalize pass must not log it either, since it would overwrite the total
	 * (workers + finalize) duration with just its own short runtime. The orchestrator logs
	 * the correct total explicitly after the finalize pass completes (see execute()).
	 *
	 * @return void
	 */
	protected function logExecutionTime(): void {
		if ( $this->input->hasParameterOption( '--worker' ) || $this->finalizeOnly ) {
			return;
		}
		parent::logExecutionTime();
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	protected function processFiles(): int {
		$this->readConfigFile( $this->config );
		$this->ensureTargetDirs();
		$this->workspace = new Workspace( new SplFileInfo( $this->dest ) );

		$this->initExecutionTime();

		$this->buckets = new DataBuckets( $this->getBucketKeys() );
		$this->buckets->loadFromWorkspace( $this->workspace );

		$this->config['worker-count'] = (int)$this->input->getOption( 'workers' );
		$this->config['worker-index'] = (int)$this->input->getOption( 'worker' );
		$this->config['compose-finalize-only'] = $this->finalizeOnly;
		$this->config['namespace-file-extensions'] = $this->namespaceFileExtensions;

		$composers = $this->makeComposers();
		$mediawikixmlbuilder = new Builder();
		foreach ( $composers as $composer ) {
			if ( $composer instanceof IDestinationPathAware ) {
				$composer->setDestinationPath( $this->dest );
			}
			$composer->buildXML( $mediawikixmlbuilder );
		}

		$this->logExecutionTime();

		return 0;
	}

	/**
	 * @param array &$config
	 *
	 * @return void
	 */
	private function readConfigFile( array &$config ): void {
		$filename = $this->input->getOption( 'config' );
		if ( !empty( $filename ) ) {
			$configOptionHelper = new ConfigOptionHelper( $filename );
			$validationError = $configOptionHelper->validateFile();

			if ( $validationError !== null ) {
				$this->output->writeln( $validationError );
				exit( 1 );
			} else {
				$config['config'] = $configOptionHelper->getConfig();
				$this->output->writeln( 'Config file loaded successfully' );
			}
		}
	}

	/**
	 *
	 * @inheritDoc
	 */
	protected function getBucketKeys(): array {
		return [];
	}

	/**
	 * ToDo: Set this method in composer to protected
	 *
	 * @return void
	 */
	private function ensureTargetDirs(): void {
		$path = "$this->dest/result";
		if ( !file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}
}
