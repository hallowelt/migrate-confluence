<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\IComposer;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Composer\DataReader\ComposerDirectDataReader;
use HalloWelt\MigrateConfluence\Composer\DataReader\IComposerDataReaderAware;
use HalloWelt\MigrateConfluence\Composer\DataWriter\ComposerDirectDataWriter;
use HalloWelt\MigrateConfluence\Composer\DataWriter\IComposerDataWriterAware;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\IMigrationConfigAware;
use HalloWelt\MigrateConfluence\IWorkspaceAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class Compose extends CommandCompose {

	private ?WorkspaceDB $workspaceDB = null;

	/**
	 * @inheritDoc
	 */
	protected function configure(): void {
		$this->setName( 'compose' );
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
	 * @return Compose
	 */
	public static function factory( array $config ): Compose {
		return new static( $config );
	}

	/**
	 * Initializes composer instances once before file processing starts.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function beforeProcessFiles(): void {
		$this->readConfigFile( $this->config );
		$this->ensureTargetDirs();
		parent::beforeProcessFiles();
	}

	/**
	 * Instantiates composer services defined in the command configuration.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function initComposers() {
		$this->composers = [];
		if ( !isset( $this->config['composers'] ) ) {
			throw new Exception( "No 'composers' key in config" );
		}

		$this->initWorkspaceDB();
		$workspace = new Workspace( new \SplFileInfo( $this->dest ) );
		$dataReader = new ComposerDirectDataReader( $this->workspaceDB );
		$dataWriter = new ComposerDirectDataWriter( $this->workspaceDB );
		$migrationConfig = new MigrationConfig( $this->config['config'] ?? [] );

		foreach ( $this->config['composers'] as $key => $callback ) {
			$composer = call_user_func_array( $callback, [] );
			if ( $composer instanceof IComposer === false ) {
				throw new Exception(
					"Factory callback for composer '$key' did not return an IComposer object"
				);
			}
			if ( $composer instanceof IOutputAwareInterface ) {
				$composer->setOutput( $this->output );
			}
			if ( $composer instanceof IComposerDataReaderAware ) {
				$composer->setDataReader( $dataReader );
			}
			if ( $composer instanceof IComposerDataWriterAware ) {
				$composer->setDataWriter( $dataWriter );
			}
			if ( $composer instanceof IMigrationConfigAware ) {
				$composer->setMigrationConfig( $migrationConfig );
			}
			if ( $composer instanceof IWorkspaceAware ) {
				$composer->setWorkspace( $workspace );
			}
			if ( $composer instanceof IDestinationPathAware ) {
				$composer->setDestinationPath( $this->dest );
			}

			$this->composers[$key] = $composer;
		}
	}

	/**
	 * @return int
	 * @throws Exception
	 */
	protected function processFiles(): int {
		$this->beforeProcessFiles();

		$mediawikixmlbuilder = new Builder();
		$overallReturn = Command::SUCCESS;
		foreach ( $this->composers as $composer ) {
			$composer->buildXML( $mediawikixmlbuilder );
		}
		$mediawikixmlbuilder->buildAndSave( $this->dest . '/result/output.xml' );

		$this->logExecutionTime();

		return $overallReturn;
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
	 * ToDo: Set this method in composer to protected
	 *
	 * @return void
	 */
	private function ensureTargetDirs(): void {
		$path = "{$this->dest}/result";
		if ( !file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}

	private function initWorkspaceDB(): void {
		if ( $this->workspaceDB === null ) {
			$this->workspaceDB = WorkspaceDB::open( $this->dest );
		}
	}
}
