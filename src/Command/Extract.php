<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Extract as CommandExtract;
use HalloWelt\MediaWiki\Lib\Migration\IExtractor;
use HalloWelt\MediaWiki\Lib\Migration\IFileProcessorEventHandler;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataReader\ExtractorDirectDataReader;
use HalloWelt\MigrateConfluence\Extractor\DataReader\IExtractorDataReaderAware;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\ExtractorDirectDataWriter;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriterAware;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWikisConfigAware;
use HalloWelt\MigrateConfluence\IWorkspaceAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class Extract extends CommandExtract {

	/** @var IExtractor[] */
	protected $extractors = [];

	/** @var array */
	protected $eventhandlers = [];

	/** @var WorkspaceDB|null */
	protected ?WorkspaceDB $workspaceDB = null;

	/**
	 * @inheritDoc
	 */
	protected function configure(): void {
		$this->setName( 'extract' );
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
	 * @return Extract
	 */
	public static function factory( array $config ): Extract {
		return new static( $config );
	}

	/**
	 * Instantiates extractor services defined in the command configuration.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function initExtractors() {
		$this->extractors = [];
		if ( !isset( $this->config['extractors'] ) ) {
			throw new Exception( "No 'extractors' key in config" );
		}

		$this->initWorkspaceDB();
		$workspace = new Workspace( new SplFileInfo( $this->dest ) );
		$dataReader = new ExtractorDirectDataReader( $this->workspaceDB );
		$dataWriter = new ExtractorDirectDataWriter( $this->workspaceDB );
		$migrationConfig = $this->getMigrationConfig();
		$wikisConfig = $this->getWikisConfig();

		$extractorFactoryCallbacks = $this->config['extractors'];
		foreach ( $extractorFactoryCallbacks as $key => $callback ) {
			$extractor = call_user_func_array( $callback, [] );
			if ( $extractor instanceof IExtractor === false ) {
				throw new Exception(
					"Factory callback for extractor '$key' did not return an "
					. "IExtractor object"
				);
			}

			if ( $extractor instanceof IOutputAwareInterface ) {
				$extractor->setOutput( $this->output );
			}
			if ( $extractor instanceof IExtractorDataReaderAware ) {
				$extractor->setDataReader( $dataReader );
			}
			if ( $extractor instanceof IExtractorDataWriterAware ) {
				$extractor->setDataWriter( $dataWriter );
			}
			if ( $extractor instanceof IMigrationConfigAware ) {
				$extractor->setMigrationConfig( $migrationConfig );
			}
			if ( $extractor instanceof IWikisConfigAware ) {
				$extractor->setWikisConfig( $wikisConfig );
			}
			if ( $extractor instanceof IWorkspaceAware ) {
				$extractor->setWorkspace( $workspace );
			}

			$this->extractors[$key] = $extractor;

			if ( $extractor instanceof IFileProcessorEventHandler ) {
				$this->eventhandlers[$key] = $extractor;
			}

		}
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
	 * @return MigrationConfig
	 */
	private function getMigrationConfig(): MigrationConfig {
		$advancedConfig = $this->readConfigFile();
		return new MigrationConfig( $advancedConfig );
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
	 * Override this method because we do not work with files anymore
	 * but with a database. So it doesn't matter which file we inject into
	 * the processFile method.
	 * Only injecting one file will force the extractor to run only once.
	 * More runs are not necessary because everything we want to extract
	 * is already part of the database.
	 */
	protected function processFiles(): int {
		$this->beforeProcessFiles();
		$this->runBeforeProcessFilesEventHandlers();

		$overallReturn = Command::SUCCESS;
		if ( count( $this->files ) > 0 ) {
			$this->currentFile = array_pop( $this->files );
			$result = $this->processFile( $this->currentFile );
			if ( $result === false ) {
				$this->output->writeln( "<error>Failed to process data</error>" );
				$overallReturn = Command::FAILURE;
			}
		} else {
			throw new Exception( 'Failed to extract data' );
		}

		$this->runAfterProcessFilesEventHandlers();
		$this->afterProcessFiles();

		return $overallReturn;
	}
}
