<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\CommandLineTools\Commands\BatchFileProcessorBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzeDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzePipeDataWriter;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\PipeToDB;
use HalloWelt\MigrateConfluence\Utility\Version;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Analyze extends BatchFileProcessorBase {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/** @var resource|false */
	private $workerPipe = false;

	/** @var ExecutionTime|null */
	private ?ExecutionTime $executionTime = null;

	/**
	 * @param array $config
	 */
	public function __construct( private readonly array $config ) {
		parent::__construct();
	}

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'analyze' );

		parent::configure();

		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config', null, InputOption::VALUE_REQUIRED, 'Specifies the path to the config yaml file'
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
		$definition->addOption(
			new InputOption(
				'worker', null, InputOption::VALUE_REQUIRED, '[Internal] Zero-based index of this worker process'
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
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$workers = (int)$input->getOption( 'workers' );
		$isChildProcess = $input->hasParameterOption( '--worker' );

		$dest = realpath( $input->getOption( 'dest' ) );
		if ( !is_dir( $dest ) ) {
			$this->output->writeln( "Destination does not exist" );
			exit();
		}

		if ( $isChildProcess ) {
			return $this->executeAsChildProcess( $dest, $input, $output );
		}

		$this->workspaceDB = WorkspaceDB::create( $dest );

		if ( $workers > 1 ) {
			$this->executionTime = new ExecutionTime();
			$result = $this->spawnWorkers( $output, $workers );
			$this->logExecutionTime( $output, $dest );
			return $result;
		}

		return parent::execute( $input, $output );
	}

	protected function processFiles(): int {
		$this->executionTime = new ExecutionTime();

		$returnValue = parent::processFiles();

		$this->logExecutionTime( $this->output );

		return $returnValue;
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 */
	protected function processFile( SplFileInfo $file ): bool {
		$this->output->writeln( "Analyzing file '{$this->currentFile->getFilename()}'" );

		$dataWriter = $this->workerPipe ? new AnalyzePipeDataWriter( new PipeToDB( $this->workerPipe ) )
			: new AnalyzeDirectDataWriter( $this->workspaceDB );

		$analyzer = new ConfluenceAnalyzer(
			$dataWriter,
			$this->workspaceDB,
			$this->output,
			$this->getMigrationConfig()
		);

		$analyzer->analyze( $file );

		return true;
	}

	/**
	 * @param string $dest
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 */
	private function executeAsChildProcess( string $dest, InputInterface $input, OutputInterface $output ): int {
		$this->workspaceDB = WorkspaceDB::open( $dest, true );

		$this->workerPipe = fopen( 'php://fd/' . PipeToDB::FILE_DESCRIPTOR, 'w' );
		if ( $this->workerPipe === false ) {
			$output->writeln( '<error>Failed to open worker pipe (fd ' . PipeToDB::FILE_DESCRIPTOR . ').</error>' );

			return Command::FAILURE;
		}

		$returnValue = parent::execute( $input, $output );

		if ( $this->workerPipe !== false ) {
			fclose( $this->workerPipe );
			$this->workerPipe = false;
		}

		return $returnValue;
	}

	/**
	 * @param OutputInterface $output
	 * @param string|null $dest
	 *
	 * @return void
	 */
	private function logExecutionTime( OutputInterface $output, ?string $dest = null ): void {
		$time = $this->executionTime->getHumanReadableTime();
		$output->writeln( "\nExecution time: {$time}\n" );

		$dest = $dest ?? $this->dest;
		$workspace = new Workspace( new SplFileInfo( $dest ) );
		$buckets = new DataBuckets( [ 'execution-time' ] );
		$buckets->loadFromWorkspace( $workspace );
		$buckets->addData( 'execution-time', $this->getName(), $time, false, true );
		$buckets->saveToWorkspace( $workspace );
	}

	/**
	 * @param OutputInterface $output
	 * @param int $workers
	 *
	 * @return int
	 */
	private function spawnWorkers( OutputInterface $output, int $workers ): int {
		$dbLog = new DBLog( $this->workspaceDB );
		$dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);

		$baseCmd = $this->buildBaseCommand();
		$descriptors = [
			0 => [
				'pipe',
				'r'
			],
			1 => [
				'pipe',
				'w'
			],
			2 => [
				'pipe',
				'w'
			],
		];
		$descriptors[PipeToDB::FILE_DESCRIPTOR] = [
			'pipe',
			'w'
		];

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
			$pipes[$i] = [
				$workerPipes[1],
				$workerPipes[2]
			];
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
					$this->storeWorkerResponse( $line, $dbLog );
					$line = fgets( $DBWritePipes[$i] );
				}
				$status = proc_get_status( $proc );
				if ( !$status['running'] ) {
					foreach ( $pipes[$i] as $pipe ) {
						// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
						while ( ( $line = fgets( $pipe ) ) !== false ) {
							$output->write( "[Worker {$i}] " . $line );
						}
						fclose( $pipe );
					}
					$line = fgets( $DBWritePipes[$i] );
					while ( $line !== false ) {
						$this->storeWorkerResponse( $line, $dbLog );
						$line = fgets( $DBWritePipes[$i] );
					}
					fclose( $DBWritePipes[$i] );
					$exitCodes[$i] = proc_close( $proc );
					$processes[$i] = null;
				}
			}
			usleep( 100000 );
		}

		foreach ( $exitCodes as $i => $exitCode ) {
			if ( $exitCode !== 0 ) {
				$output->writeln( "<error>Worker {$i} failed with exit code {$exitCode}.</error>" );

				return Command::FAILURE;
			}
		}

		$output->writeln( '<info>All workers completed successfully.</info>' );

		return Command::SUCCESS;
	}

	/**
	 * @param string $line
	 * @param DBLog $dbLog
	 *
	 * @return void
	 */
	private function storeWorkerResponse( string $line, DBLog $dbLog ): void {
		$data = json_decode( $line, true );
		if ( is_array( $data ) && count( $data ) > 1 ) {
			$method = array_shift( $data );
			if ( $method === 'log' ) {
				$dbLog->addLogEntry( ...$data );
			} else {
				call_user_func_array(
					[
						$this->workspaceDB,
						$method
					],
					$data
				);
			}
		} else {
			$dbLog->addLogEntry(
				'error',
				'analyze.invalid-worker-output',
				__CLASS__,
				$line
			);
		}
	}

	/**
	 * @return array
	 */
	private function buildBaseCommand(): array {
		$argv = $_SERVER['argv'];
		$cmd = [
			PHP_BINARY,
			$argv[0]
		];

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
	 * @return MigrationConfig
	 */
	private function getMigrationConfig(): MigrationConfig {
		$filename = $this->input->getOption( 'config' );

		$configOptionHelper = new ConfigOptionHelper( $filename );
		$validationError = $configOptionHelper->validateFile();

		if ( $validationError !== null ) {
			$this->output->writeln( $validationError );
			exit( 1 );
		} else {
			$advancedConfig = $configOptionHelper->getConfig();
			$this->output->writeln( 'Config file loaded successfully' );
		}

		return new MigrationConfig( $advancedConfig );
	}

	/**
	 * Filter to entities.xml files only, then slice to this worker's subset.
	 */
	protected function makeFileList(): void {
		parent::makeFileList();

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
	 * @return array
	 */
	protected function makeExtensionWhitelist(): array {
		if ( isset( $this->config['file-extension-whitelist'] ) ) {
			return $this->config['file-extension-whitelist'];
		}

		return [];
	}
}
