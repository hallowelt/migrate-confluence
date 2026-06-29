<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Analyze as CommandAnalyze;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Analyzer\IPipeSender;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Analyze extends CommandAnalyze {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var DBLog */
	private DBLog $dbLog;

	/** @var resource|false */
	private $workerPipe = false;

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
	 * @return Analyze
	 */
	public static function factory( array $config ): Analyze {
		return new static( $config );
	}

	/**
	 * Intercept execution: when --workers > 1 and this is not already a spawned worker,
	 * act as the orchestrator and launch child processes.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$workers = (int)$input->getOption( 'workers' );
		$isWorker = $input->hasParameterOption( '--worker' );

		if ( !$isWorker ) {
			$this->setupDB( $input );
		} else {
			$dest = realpath( $input->getOption( 'dest' ) );
			$this->workspaceDB = new WorkspaceDB( $dest . '/workspace.sqlite', true );
			$this->dbLog = new DBLog( $this->workspaceDB );
		}

		if ( $workers > 1 && !$isWorker ) {
			return $this->spawnWorkers( $input, $output, $workers );
		}

		if ( $isWorker ) {
			$this->workerPipe = fopen( 'php://fd/' . PipeToDB::FILE_DESCRIPTOR, 'w' );
			if ( $this->workerPipe === false ) {
				$output->writeln( '<error>Failed to open worker pipe (fd ' . PipeToDB::FILE_DESCRIPTOR . ').</error>' );
				return Command::FAILURE;
			}
		}

		$returnValue = parent::execute( $input, $output );

		if ( $this->workerPipe !== false ) {
			fclose( $this->workerPipe );
			$this->workerPipe = false;
		}

		return $returnValue;
	}

	/**
	 * Delete any existing database, create a fresh one, and log the run.
	 *
	 * @param InputInterface $input
	 */
	private function setupDB( InputInterface $input ): void {
		$dest = realpath( $input->getOption( 'dest' ) );
		$dbPath = $dest . '/workspace.sqlite';

		if ( file_exists( $dbPath ) ) {
			unlink( $dbPath );
		}

		$this->workspaceDB = new WorkspaceDB( $dbPath );
		$this->dbLog = new DBLog( $this->workspaceDB );

		$this->dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doProcessFile(): bool {
		$this->readConfigFile( $this->config );
		$this->output->writeln( "Analyzing file '{$this->currentFile->getFilename()}'" );
		$analyzerFactoryCallbacks = $this->config['analyzers'];
		foreach ( $analyzerFactoryCallbacks as $key => $callback ) {
			$analyzer = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace, $this->buckets ]
			);
			if ( $analyzer instanceof IAnalyzer === false ) {
				throw new Exception(
					"Factory callback for analyzer '$key' did not return an "
					. "IAnalyzer object"
				);
			}
			if ( $analyzer instanceof IOutputAwareInterface ) {
				$analyzer->setOutput( $this->output );
			}
			if ( $analyzer instanceof IDestinationPathAware ) {
				$analyzer->setDestinationPath( $this->dest );
			}
			if ( $analyzer instanceof IPipeSender && $this->workerPipe !== false ) {
				$analyzer->setPipe( $this->workerPipe );
			}
			$result = $analyzer->analyze( $this->currentFile );
			// TODO: Evaluate result
		}
		return true;
	}

	/**
	 * Spawn $workers child processes, each handling a disjoint slice of the
	 * entities.xml file list, and stream their combined output until all are done.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param int $workers
	 * @return int
	 */
	private function spawnWorkers( InputInterface $input, OutputInterface $output, int $workers ): int {
		$baseCmd = $this->buildBaseCommand();
		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];
		$descriptors[PipeToDB::FILE_DESCRIPTOR] = [ 'pipe', 'w' ];

		$processes = [];
		$pipes = [];
		$DBWritePipes = [];

		for ( $i = 0; $i < $workers; $i++ ) {
			$cmd = array_merge( $baseCmd, [ '--worker=' . $i ] );
			$cmdString = implode( ' ', array_map( 'escapeshellarg', $cmd ) );
			$output->writeln( "Starting worker {$i}: <comment>{$cmdString}</comment>" );
			// phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.proc_open
			$proc = proc_open( $cmdString, $descriptors, $workerPipes );
			if ( $proc === false ) {
				$output->writeln( "<error>Failed to start worker {$i}.</error>" );
				return Command::FAILURE;
			}
			stream_set_blocking( $workerPipes[1], false );
			stream_set_blocking( $workerPipes[2], false );
			stream_set_blocking( $workerPipes[PipeToDB::FILE_DESCRIPTOR], false );
			fclose( $workerPipes[0] );
			$processes[$i] = $proc;
			$pipes[$i] = [ $workerPipes[1], $workerPipes[2] ];
			$DBWritePipes[$i] = $workerPipes[3];
		}

		$exitCodes = array_fill( 0, $workers, null );
		while ( count( array_filter( $processes ) ) > 0 ) {
			foreach ( $processes as $i => $proc ) {
				if ( $proc === null ) {
					continue;
				}
				foreach ( $pipes[$i] as $pipe ) {
					$line = fgets( $pipe );
					while ( $line !== false ) {
						$output->write( "[Worker {$i}] " . $line );
						$line = fgets( $pipe );
					}
				}
				$line = fgets( $DBWritePipes[$i] );
				while ( $line !== false ) {
					$this->storeWorkerResponse( $line );
					$line = fgets( $DBWritePipes[$i] );
				}
				$status = proc_get_status( $proc );
				if ( !$status['running'] ) {
					// Drain any remaining output
					foreach ( $pipes[$i] as $pipe ) {
						// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
						while ( ( $line = fgets( $pipe ) ) !== false ) {
							$output->write( "[Worker {$i}] " . $line );
						}
						fclose( $pipe );
					}
					$line = fgets( $DBWritePipes[$i] );
					while ( $line !== false ) {
						$this->storeWorkerResponse( $line );
						$line = fgets( $DBWritePipes[$i] );
					}
					fclose( $DBWritePipes[$i] );
					$exitCodes[$i] = proc_close( $proc );
					$processes[$i] = null;
					$output->writeln( "Worker {$i} finished with exit code {$exitCodes[$i]}." );
				}
			}
			usleep( 50000 );
		}

		$failed = array_filter( $exitCodes, static function ( $code ) {
			return $code !== Command::SUCCESS;
		} );

		if ( !empty( $failed ) ) {
			$failedList = implode( ', ', array_keys( $failed ) );
			$output->writeln( "<error>One or more workers failed: workers {$failedList}</error>" );
			return Command::FAILURE;
		}

		$output->writeln( '<info>All workers completed successfully.</info>' );
		return Command::SUCCESS;
	}

	/**
	 * Decode a JSON line from a worker pipe and persist it to the DB.
	 */
	private function storeWorkerResponse( string $line ): void {
		$data = json_decode( $line, true );
		if ( is_array( $data ) && count( $data ) > 1 ) {
			$method = array_shift( $data );
			if ( $method === 'log' ) {
				$this->dbLog->addLogEntry( ...$data );
			} else {
				call_user_func_array( [ $this->workspaceDB, $method ], $data );
			}
		} else {
			$this->dbLog->addLogEntry(
				'error',
				'analyze.invalid-worker-output',
				__CLASS__,
				$line
			);
		}
	}

	/**
	 * Reconstruct the command array (PHP binary + script + current arguments),
	 * stripping any pre-existing --worker flag.
	 *
	 * @return string[]
	 */
	private function buildBaseCommand(): array {
		$argv = $_SERVER['argv'];
		$cmd = [ PHP_BINARY, $argv[0] ];

		for ( $i = 1; $i < count( $argv ); $i++ ) {
			$arg = $argv[$i];
			if ( preg_match( '#^--worker(=.*)?$#', $arg ) ) {
				if ( $arg === '--worker' ) {
					$i++;
				}
				continue;
			}
			$cmd[] = $arg;
		}

		return $cmd;
	}

	/**
	 * Filter the file list to entities.xml files only, then slice to the
	 * subset belonging to this worker.
	 */
	protected function makeFileList(): void {
		parent::makeFileList();

		// Keep only entities.xml files — the ConfluenceAnalyzer skips all others anyway,
		// but filtering here gives accurate worker slicing.
		$this->files = array_filter(
			$this->files,
			static function ( $file ) {
				return $file->getFilename() === 'entities.xml';
			}
		);

		if ( !$this->input->hasParameterOption( '--worker' ) ) {
			return;
		}

		$workers = (int)$this->input->getOption( 'workers' );
		$worker = (int)$this->input->getOption( 'worker' );

		$index = 0;
		$filtered = [];
		foreach ( $this->files as $path => $file ) {
			if ( $index % $workers === $worker ) {
				$filtered[$path] = $file;
			}
			$index++;
		}
		$this->files = $filtered;
	}

	/**
	 *
	 * @inheritDoc
	 */
	protected function getBucketKeys(): array {
		return [];
	}

	/**
	 * @param array &$config
	 *
	 * @return void
	 */
	private function readConfigFile( array &$config ): void {
		$filename = $this->input->getOption( 'config' );
		if ( is_string( $filename ) && is_file( realpath( $filename ) ) ) {
			$content = file_get_contents( realpath( $filename ) );
			if ( $content ) {
				try {
					$yaml = Yaml::parse( $content );
					$config = array_merge( $config, $yaml );
				} catch ( ParseException $e ) {
					$this->output->writeln( 'Invalid config file provided' );
					exit( 1 );
				}
			}
		}
	}
}
