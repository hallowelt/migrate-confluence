<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Convert as CommandConvert;
use HalloWelt\MediaWiki\Lib\Migration\IConverter;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Converter\IPipeSender;
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

class Convert extends CommandConvert {

	/** @var string */
	private string $wikiTextBasePath = '';

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var DBLog */
	private DBLog $dbLog;

	/** @var resource|false */
	private $pipe = false;

	/**
	 * @return void
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
	 * @return Convert
	 */
	public static function factory( array $config ): Convert {
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

		$this->setupDB( $input, !$isWorker );

		if ( $workers > 1 && !$isWorker ) {
			return $this->spawnWorkers( $input, $output, $workers );
		}

		/* this is the "single worker" case. Here we define our own pipe for the converter to
		 * send data to. */
		if ( !$isWorker ) {
			$this->pipe = fopen( 'php://temp', 'r+' );
		}

		$returnValue = parent::execute( $input, $output );

		if ( $this->pipe !== false ) {
			rewind( $this->pipe );
			$line = fgets( $this->pipe );
			while ( $line !== false ) {
				$this->storeWorkerResponse( $line );
				$line = fgets( $this->pipe );
			}
			fclose( $this->pipe );
		}

		return $returnValue;
	}

	/**
	 * set up DB connection
	 *
	 * We use this connection here in order to prevent multiple write connections to the DB in
	 * the case of more than one worker.
	 *
	 * @param InputInterface $input
	 * @param bool $logUsage mention the run in the log, if this is either the only or the host process
	 */
	private function setupDB( InputInterface $input, bool $logUsage ): void {
		/* dest is never set if parent::execute is not called. This is unfortunately
		 * the case in the host/worker situation */
		$this->dest = realpath( $input->getOption( 'dest' ) );

		$this->workspaceDB = new WorkspaceDB( $this->dest . '/workspace.sqlite' );
		$this->dbLog = new DBLog( $this->workspaceDB );

		if ( $logUsage ) {
			$this->dbLog->addLogEntry(
				'info',
				'convert',
				__CLASS__,
				sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
			);
		}
	}

	protected function doProcessFile(): bool {
		$converterFactoryCallbacks = $this->config['converters'];

		$this->wikiTextBasePath = $this->dest . '/content/wikitext';
		$this->makeTargetPathname();
		$this->ensureTargetPath();

		$this->readConfigFile( $this->config );

		foreach ( $converterFactoryCallbacks as $key => $callback ) {
			$converter = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace, $this->pipe ]
			);
			if ( $converter instanceof IConverter === false ) {
				throw new Exception(
					"Factory callback for converter '$key' did not return an "
					. "IConverter object"
				);
			}
			if ( $converter instanceof IOutputAwareInterface ) {
				$converter->setOutput( $this->output );
			}
			if ( $converter instanceof IDestinationPathAware ) {
				$converter->setDestinationPath( $this->dest );
			}
			if ( $converter instanceof IPipeSender ) {
				$converter->setPipe( $this->pipe );
			}

			$result = $converter->convert( $this->currentFile );

			file_put_contents( $this->targetPathname, $result );
		}
		return true;
	}

	/**
	 * Spawn $workers child processes, each handling a disjoint slice of the file list,
	 * and stream their combined output until all are done.
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
	 *
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
				'convert.invalid-worker-output',
				__CLASS__,
				$line
			);
		}
	}

	/**
	 * Reconstruct the command array (PHP binary + script + current arguments)
	 * without the --workers value, so children can receive it unmodified,
	 * and without any pre-existing --worker flag.
	 *
	 * @return string[]
	 */
	private function buildBaseCommand(): array {
		$argv = $_SERVER['argv'];
		$cmd = [ PHP_BINARY, $argv[0] ];

		for ( $i = 1; $i < count( $argv ); $i++ ) {
			$arg = $argv[$i];
			// Strip any --worker option that was somehow passed to the orchestrator
			if ( preg_match( '#^--worker(=.*)?$#', $arg ) ) {
				// skip value token too if separate
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
	 * Filter the file list to only the slice belonging to this worker.
	 */
	protected function makeFileList() {
		parent::makeFileList();

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
					exit( true );
				}
			}
		}
	}

	private function makeTargetPathname() {
		$this->targetPathname = str_replace(
			$this->src,
			$this->wikiTextBasePath,
			$this->currentFile->getPathname()
		);
		$this->targetPathname = preg_replace( '#\.mraw$#', '.wiki', $this->targetPathname );
	}

	private function ensureTargetPath() {
		$baseTargetPath = dirname( $this->targetPathname );
		if ( !file_exists( $baseTargetPath ) ) {
			mkdir( $baseTargetPath, 0755, true );
		}
	}

}
