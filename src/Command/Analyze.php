<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\AnalyzeWorkers as CommandAnalyzeWorkers;
use HalloWelt\MediaWiki\Lib\Migration\IAnalyzer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerPipeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriterAware;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWikisConfigAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use HalloWelt\MigrateConfluence\Utility\WikisOptionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Analyze extends CommandAnalyzeWorkers {

	/** @var WorkspaceDB|null */
	private ?WorkspaceDB $workspaceDB = null;

	/** @var DBLog|null */
	private ?DBLog $dbLog = null;

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
			),
		);
		$definition->addOption(
			new InputOption(
				'wikis',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the csv file containing interwiki configuration'
			)
		);
	}

	/**
	 * @param array $config
	 */
	private function __construct( protected array $config ) {
		parent::__construct();
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
	 *
	 * The shared worker base handles orchestration, so this method only supplies
	 * the Analyze-specific storage writers and delegates the actual run back to
	 * the inherited command execution flow.
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
	 * Build the Analyze command's parent-process writer.
	 *
	 * The direct writer is backed by the workspace database and also records the
	 * command-level log entry that documents which migration version produced the
	 * current workspace state.
	 */
	protected function getDataWriter(): IAnalyzeDataWriter {
		$this->initWorkspaceDB();
		$this->initWorkspaceDbLog();

		// Must happen here, not in initAnalyzers(): with --workers > 1 the parent never
		// runs the analyzer setup, so this is the only place the CSV reaches the DB
		// before the children start reading it.
		$this->readWikisConfigFile( $this->workspaceDB );

		$this->dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);

		return new AnalyzerDirectDataWriter( $this->workspaceDB );
	}

	/**
	 * Build the Analyze command's worker-process writer.
	 *
	 * Workers do not touch the storage backend directly; they serialize all
	 * updates into the pipe so the parent process can apply them safely.
	 */
	protected function getWorkerDataWriter(): IAnalyzeDataWriter {
		return new AnalyzerPipeDataWriter( new PipeChannel() );
	}

	/**
	 * Instantiates analyzer services defined in the command configuration.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function initAnalyzers() {
		$migrationConfig = $this->getMigrationConfig();
		$wikisConfig = $this->getWikisConfig();

		$this->analyzers = [];

		$analyzerFactoryCallbacks = $this->config['analyzers'] ?? [];
		foreach ( $analyzerFactoryCallbacks as $key => $callback ) {
			$analyzer = call_user_func_array( $callback, []	);
			if ( $analyzer instanceof IAnalyzer === false ) {
				throw new Exception(
					"Factory callback for analyzer '$key' did not return an "
					. "IAnalyzer object"
				);
			}
			if ( $analyzer instanceof IOutputAwareInterface ) {
				$analyzer->setOutput( $this->output );
			}
			if ( $analyzer instanceof IAnalyzeDataWriterAware ) {
				$analyzer->setDataWriter( $this->dataWriter );
			}
			if ( $analyzer instanceof IMigrationConfigAware ) {
				$analyzer->setMigrationConfig( $migrationConfig );
			}
			if ( $analyzer instanceof IWikisConfigAware ) {
				$analyzer->setWikisConfig( $wikisConfig );
			}

			$this->analyzers[$key] = $analyzer;
		}
	}

	/**
	 * @return bool
	 */
	protected function doProcessFile(): bool {
		$this->output->writeln( "Analyzing file '{$this->currentFile->getFilename()}'" );
		$success = true;
		foreach ( $this->analyzers as $analyzer ) {
			if ( !$analyzer->analyze( $this->currentFile ) ) {
				$success = false;
			}
		}
		return $success;
	}

	protected function logExecutionTime(): void {
		$time = $this->getExecutionTime();
		$this->output->writeln( "\nExecution time: {$time}\n" );
		if ( $this->workspaceDB === null ) {
			$this->initWorkspaceDB();
		}

		$this->dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			"Execution time: {$time}"
		);
	}

	/**
	 * Initializes the workspace database if it hasn't been initialized yet.
	 *
	 * @return void
	 */
	private function initWorkspaceDB(): void {
		if ( $this->workspaceDB === null ) {
			if ( $this->isWorkerProcess() ) {
				$this->workspaceDB = WorkspaceDB::open( $this->dest, true );
			} else {
				$this->workspaceDB = WorkspaceDB::create( $this->dest );
			}
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

	/**
	 * @return WikisConfig
	 */
	private function getWikisConfig(): WikisConfig {
		if ( $this->workspaceDB === null ) {
			$this->initWorkspaceDB();
		}
		// The CSV is imported by the parent process in getDataWriter().
		return new WikisConfig( $this->workspaceDB );
	}

	/**
	 * @return array
	 */
	private function readConfigFile(): array {
		$config = [];
		$filename = $this->input->getOption( 'config' );
		if ( !empty( $filename ) ) {
			$configOptionHelper = new ConfigOptionHelper( $filename );
			$validationError = $configOptionHelper->validateFile();

			if ( $validationError !== null ) {
				$this->output->writeln( $validationError );
				exit( 1 );
			} else {
				$config = $configOptionHelper->getConfig();
				$this->output->writeln( 'Config file loaded successfully' );
			}
		}

		return $config;
	}

	/**
	 * @return MigrationConfig
	 */
	private function getMigrationConfig(): MigrationConfig {
		$advancedConfig = $this->readConfigFile();
		return new MigrationConfig( $advancedConfig );
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 *
	 * @return void
	 */
	private function readWikisConfigFile( WorkspaceDB $workspaceDB ): void {
		$filename = $this->input->getOption( 'wikis' );
		if ( !empty( $filename ) ) {
			$wikiConfigOptionHelper = new WikisOptionHelper( $filename );
			$validationError = $wikiConfigOptionHelper->validateFile();
			if ( $validationError !== null ) {
				$this->output->writeln( $validationError );
				exit( 1 );
			}

			foreach ( $wikiConfigOptionHelper->getConfig() as $wikiConfig ) {
				$workspaceDB->addWikisConfig(
					$wikiConfig['space-key'],
					$wikiConfig['wiki-name'],
					$wikiConfig['wiki-namespace'],
					$wikiConfig['wiki-root-page']
				);
			}
		}
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

		// Split the filtered file list across workers after the command-specific filter.
		$this->sliceFilesForCurrentWorker();
	}
}
