<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Command\Traits\ParallelCommandTrait;
use HalloWelt\MigrateConfluence\Command\Traits\SetupHooks;
use HalloWelt\MigrateConfluence\Database\DataWriter\ComposeDataWriter;
use HalloWelt\MigrateConfluence\Database\DataWriter\WorkerPool;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\DirectoryHelper;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Compose command with parallel processing support.
 * Orchestrates worker processes and aggregates results with finalize pass.
 */
class Compose extends CommandCompose {

	use ParallelCommandTrait;
	use SetupHooks;

	/**
	 * @var bool Tracks whether we're in the finalize-only pass.
	 */
	private bool $isInFinalizePass = false;

	/**
	 * @var array<string,string[]> subDir (wikiName/namespace) => file extensions, collected
	 * in memory from all workers via ComposeDataWriter, for the finalize pass to consume.
	 */
	protected array $namespaceFileExtensions = [];

	/**
	 * Whether this is the finalize-only pass that runs after all compose workers have finished.
	 *
	 * @return bool
	 */
	protected function isFinalStep(): bool {
		return $this->isInFinalizePass;
	}

	/**
	 * Set whether we're in the finalize-only pass.
	 *
	 * @param bool $isFinalize
	 * @return void
	 */
	protected function setFinalStep( bool $isFinalize ): void {
		$this->isInFinalizePass = $isFinalize;
	}

	/**
	 * @inheritDoc
	 */
	protected function configure(): void {
		parent::configure();
		$this->configureParallelOptions();
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the config yaml file'
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
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$this->input = $input;
		$this->output = $output;

		$this->readConfigFile( $this->config );
		$this->installCustomerHooks( $this->config, $this->input->getOption( 'config' ) );

		return $this->executeParallel( $input, $output );
	}

	/**
	 * Orchestrate parallel worker execution and finalize pass.
	 * When --workers > 1 and not already a spawned worker, launches child processes.
	 * Namespaces/wikis are round-robin sliced across workers regardless of size.
	 * Only the single (non-parallel) run writes to the compose DB log.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	private function executeParallel( InputInterface $input, OutputInterface $output ): int {
		$workers = (int)$input->getOption( 'workers' );
		$isChildProcess = $input->hasParameterOption( '--worker' );

		if ( !$isChildProcess && $workers > 1 ) {
			$this->dest = realpath( $input->getOption( 'dest' ) );
			if ( !is_dir( $this->dest ) ) {
				Log::to( 'console' )->error( "Destination does not exist" );
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
			$this->setFinalStep( true );
			$result = parent::execute( $input, $output );
			$this->setFinalStep( false );

			// Log the total (workers + finalize) execution time once, now that finalize's
			// own processFiles() run has set up $this->workspace/executionTimeBuckets.
			$this->logExecutionTime();

			return $result;
		}

		return parent::execute( $input, $output );
	}

	/**
	 * @inheritDoc
	 */
	protected function initExecutionTime(): void {
		if ( $this->isFinalStep() ) {
			$this->preserveExecutionTime();
			return;
		}
		parent::initExecutionTime();
	}

	/**
	 * @inheritDoc
	 */
	protected function logExecutionTime(): void {
		if ( $this->shouldSkipExecutionTimeLogging() || $this->isFinalStep() ) {
			return;
		}
		parent::logExecutionTime();
	}

	/**
	 * Pass parallel processing configuration to composers.
	 *
	 * @param InputInterface $input
	 * @return void
	 */
	private function passParallelConfigToComposers( InputInterface $input ): void {
		$this->config['worker-count'] = (int)$input->getOption( 'workers' );
		$this->config['worker-index'] = (int)$input->getOption( 'worker' );
		$this->config['compose-finalize-only'] = $this->isFinalStep();
		$this->config['namespace-file-extensions'] = $this->namespaceFileExtensions;
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	protected function processFiles(): int {
		$this->ensureTargetDirs();
		$this->workspace = new Workspace( new SplFileInfo( $this->dest ) );

		$this->initExecutionTime();

		$this->buckets = new DataBuckets( $this->getBucketKeys() );
		$this->buckets->loadFromWorkspace( $this->workspace );

		$this->passParallelConfigToComposers( $this->input );

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
				Log::to( 'console' )->error( $validationError );
				exit( 1 );
			} else {
				$config['config'] = $configOptionHelper->getConfig();
				Log::to( 'console' )->debug( 'Config file loaded successfully' );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getBucketKeys(): array {
		return [];
	}

	/**
	 * Ensure target directories exist.
	 *
	 * @return void
	 */
	private function ensureTargetDirs(): void {
		DirectoryHelper::ensure( "$this->dest/result" );
	}
}
