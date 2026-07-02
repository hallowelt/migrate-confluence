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
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WorkerPool;
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
		$isChildProcess = $input->hasParameterOption( '--worker' );

		$this->setupDB( $input, !$isChildProcess );

		/**
		 *  Spawn $workers child processes, each handling a disjoint slice of the file list,
		 *  and stream their combined output until all are done.
		 */
		if ( $workers > 1 && !$isChildProcess ) {
			$pool = new WorkerPool( $output, $this->workspaceDB, $this->dbLog, 50000 );
			return $pool->run( WorkerPool::buildBaseCommand(), $workers );
		}

		/* this is the "single worker" case. Here we define our own pipe for the converter to
		 * send data to. */
		if ( !$isChildProcess ) {
			$this->pipe = fopen( 'php://temp', 'r+' );
		}

		$returnValue = parent::execute( $input, $output );

		if ( $this->pipe !== false ) {
			$pool = new WorkerPool( $output, $this->workspaceDB, $this->dbLog );
			rewind( $this->pipe );
			$line = fgets( $this->pipe );
			while ( $line !== false ) {
				$pool->storeResponse( $line );
				$line = fgets( $this->pipe );
			}
			$pipe = $this->pipe;
			$this->pipe = false;
			fclose( $pipe );
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

		$this->workspaceDB = WorkspaceDB::openExisting( $this->dest . '/workspace.sqlite' );
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
	 * Filter the file list to only the slice belonging to this worker.
	 */
	protected function makeFileList(): void {
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
					exit( 1 );
				}
			}
		}
	}

	private function makeTargetPathname(): void {
		$this->targetPathname = str_replace(
			$this->src,
			$this->wikiTextBasePath,
			$this->currentFile->getPathname()
		);
		$this->targetPathname = preg_replace( '#\.mraw$#', '.wiki', $this->targetPathname );
	}

	private function ensureTargetPath(): void {
		$baseTargetPath = dirname( $this->targetPathname );
		if ( !file_exists( $baseTargetPath ) ) {
			mkdir( $baseTargetPath, 0755, true );
		}
	}

}
