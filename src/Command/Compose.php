<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\DataWriter\NullDataWriter;
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

			$pool = new WorkerPool( $output, new NullDataWriter() );
			$result = $pool->run( WorkerPool::baseCommandFromArgv(), $workers );
			if ( $result !== Command::SUCCESS ) {
				return $result;
			}

			// Workers only produced per-namespace artifacts. Run a single, non-parallel
			// finalize pass in-process to aggregate the once-per-wiki artifacts
			// (deployment.txt, wikiimport.sh, shared content, wiki-level sidebar) and the
			// version-log DB write.
			$this->finalizeOnly = true;
			$result = parent::execute( $input, $output );
			$this->finalizeOnly = false;
			return $result;
		}

		return parent::execute( $input, $output );
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
