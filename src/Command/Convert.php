<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\ConvertWorkers as CommandConvertWorkers;
use HalloWelt\MediaWiki\Lib\Migration\Database\DataReader\IDataReader;
use HalloWelt\MediaWiki\Lib\Migration\IConverter;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReader;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReaderAware;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterPipeDataWriter;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriterAware;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Convert extends CommandConvertWorkers {

	/** @var string */
	private string $wikiTextBasePath = '';

	/** @var WorkspaceDB|null */
	private ?WorkspaceDB $workspaceDB = null;

	/** @var DBLog|null */
	private ?DBLog $dbLog = null;

	/**
	 * @return void
	 */
	protected function configure(): void {
		$this->setName( 'convert' );
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
	}

	/**
	 * @param array $config
	 */
	public function __construct( protected array $config ) {
		parent::__construct();
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
	 * Convert follows the same worker orchestration as Analyze, but its storage
	 * target is the wikitext workspace tree rather than the analysis database.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		return $this->executeWithWorkers(
			$input,
			$output,
			function () use ( $input, $output ): int {
				return parent::execute( $input, $output );
			}
		);
	}

	/**
	 * Build the Convert command's parent-process writer.
	 *
	 * The direct writer is created from the destination workspace database and
	 * is also responsible for the top-level migration log entry.
	 */
	protected function getDataWriter(): IConverterDataWriter {
		$this->initWorkspaceDB();
		$this->initWorkspaceDbLog();

		$this->dbLog->addLogEntry(
			'info',
			'convert',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);

		return new ConverterDirectDataWriter( $this->workspaceDB );
	}

	/**
	 * Build the Convert command's worker-process writer.
	 *
	 * The worker-side writer sends structured records back to the parent process
	 * so the parent can apply all storage mutations in one place.
	 */
	protected function getWorkerDataWriter(): IConverterDataWriter {
		return new ConverterPipeDataWriter( new \HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel() );
	}

	protected function logExecutionTime(): void {
		$time = $this->getExecutionTime();
		$this->output->writeln( "\nExecution time: {$time}\n" );
		if ( $this->isWorkerProcess() ) {
			return;
		}
		if ( $this->workspaceDB === null ) {
			$this->initWorkspaceDB();
		}
		$this->initWorkspaceDbLog();

		$this->dbLog->addLogEntry(
			'info',
			'convert',
			__CLASS__,
			"Execution time: {$time}"
		);
	}

	/**
	 * Instantiates converter services defined in the command configuration.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function initConverters() {
		if ( !$this->dataWriter instanceof IConverterDataWriter ) {
			throw new Exception( 'No data writer is set' );
		}

		$this->converters = [];
		$converterFactoryCallbacks = $this->config['converters'];
		$migrationConfig = $this->getMigrationConfig();
		if ( !$this->dataReader instanceof IConverterDataReader ) {
			throw new Exception( 'No converter data reader is set' );
		}

		foreach ( $converterFactoryCallbacks as $key => $callback ) {
			$converter = call_user_func_array( $callback, [] );
			if ( $converter instanceof IConverter === false ) {
				throw new Exception(
					"Factory callback for converter '$key' did not return an "
					. "IConverter object"
				);
			}
			if ( $converter instanceof IConverterDataReaderAware ) {
				$converter->setDataReader( $this->dataReader );
			}
			if ( $converter instanceof IMigrationConfigAware ) {
				$converter->setMigrationConfig( $migrationConfig );
			}
			if ( $converter instanceof IConverterDataWriterAware ) {
				$converter->setDataWriter( $this->dataWriter );
			}
			if ( $converter instanceof IOutputAwareInterface ) {
				$converter->setOutput( $this->output );
			}
			if ( $converter instanceof IDestinationPathAware ) {
				$converter->setDestinationPath( $this->dest );
			}

			$this->converters[$key] = $converter;
		}
	}

	/**
	 * @throws Exception
	 */
	protected function doProcessFile(): bool {
		if ( !$this->dataWriter instanceof IConverterDataWriter ) {
			throw new Exception( 'No data writer is set' );
		}

		$this->wikiTextBasePath = $this->dest . '/content/wikitext';
		$this->makeTargetPathname();
		$this->ensureTargetPath();

		$success = true;
		foreach ( $this->converters as $converter ) {
			$result = $converter->convert( $this->currentFile );
			if ( $result === null ) {
				$success = false;
				$this->output->writeln(
					"Failed to convert '{$this->currentFile->getFilename()}'"
				);
				continue;
			}

			file_put_contents( $this->targetPathname, $result );
		}
		return $success;
	}

	/**
	 * Filter the file list to only the slice belonging to this worker.
	 */
	protected function makeFileList(): void {
		parent::makeFileList();

		// Split the converted files only after the base path mapping is in place.
		$this->sliceFilesForCurrentWorker();
	}

	/**
	 * @return MigrationConfig
	 */
	private function getMigrationConfig(): MigrationConfig {
		$advancedConfig = [];
		$this->readConfigFile( $advancedConfig );

		return new MigrationConfig( $advancedConfig['config'] ?? [] );
	}

	/**
	 * The parent uses a readonly connection for conversion lookups, distinct from
	 * its direct writer connection.
	 */
	protected function getDataReader(): ?IDataReader {
		return new ConverterDirectDataReader( WorkspaceDB::open( $this->dest, true ) );
	}

	/**
	 * Each worker gets its own readonly lookup connection. All mutations continue
	 * through ConverterPipeDataWriter and are replayed by the parent writer.
	 */
	protected function getWorkerDataReader(): ?IDataReader {
		return new ConverterDirectDataReader( WorkspaceDB::open( $this->dest, true ) );
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

	/**
	 * Initializes the workspace database if it hasn't been initialized yet.
	 *
	 * @return void
	 */
	private function initWorkspaceDB(): void {
		if ( $this->workspaceDB === null ) {
			$this->workspaceDB = WorkspaceDB::open( $this->dest );
		}
	}

	/**
	 * Initializes reusable workspace database and log helper instances.
	 *
	 * @return void
	 */
	private function initWorkspaceDbLog(): void {
		if ( $this->workspaceDB !== null && $this->dbLog !== null ) {
			return;
		}

		if ( $this->workspaceDB === null ) {
			$this->initWorkspaceDB();
		}

		$this->dbLog = new DBLog( $this->workspaceDB );
	}

}
