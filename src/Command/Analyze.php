<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\CommandLineTools\Commands\BatchFileProcessorBase;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Analyzer\ConfluenceAnalyzer;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerDirectDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\AnalyzerPipeDataWriter;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Database\DataWriter\WorkerPool;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\CSVParser;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Version;
use HalloWelt\MigrateConfluence\Utility\WikisConfig;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Analyze extends BatchFileProcessorBase {

	/** @var ExecutionTime|null */
	private ?ExecutionTime $executionTime = null;

	/** @var IAnalyzeDataWriter|null */
	private ?IAnalyzeDataWriter $dataWriter;

	/**
	 * @var WikisConfig
	 */
	private WikisConfig $wikisConfig;

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
				'wikis',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the csv file containing interwiki configuration'
			)
		);
		$definition->addOption(
			new InputOption(
				'usermap',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the csv file mapping Confluence usernames to MediaWiki usernames'
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
		$this->input = $input;
		$this->output = $output;

		$workers = (int)$input->getOption( 'workers' );
		$isChildProcess = $input->hasParameterOption( '--worker' );

		$this->dest = realpath( $input->getOption( 'dest' ) );
		if ( !is_dir( $this->dest ) ) {
			$output->writeln( "Destination does not exist" );
			return Command::FAILURE;
		}

		if ( $isChildProcess ) {
			$workspaceDB = WorkspaceDB::open( $this->dest );
			$this->wikisConfig = new WikisConfig( $workspaceDB );
			$this->dataWriter = new AnalyzerPipeDataWriter( new PipeChannel() );

			try {
				return parent::execute( $input, $output );
			} finally {
				$this->dataWriter = null;
			}
		}

		$workspaceDB = WorkspaceDB::create( $this->dest );
		$this->readWikisConfigFile( $workspaceDB );
		if ( $this->getMigrationConfig()->getComposerAddUserinfo() ) {
			$this->readUsermapFile( $workspaceDB );
		}
		$this->wikisConfig = new WikisConfig( $workspaceDB );

		$dbLog = new DBLog( $workspaceDB );
		$dbLog->addLogEntry(
			'info',
			'analyze',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);

		if ( $workers > 1 ) {
			$pool = new WorkerPool( $output, new AnalyzerDirectDataWriter( $workspaceDB ) );

			$this->executionTime = new ExecutionTime();
			$result = $pool->run( WorkerPool::baseCommandFromArgv(), $workers );
			$this->logExecutionTime( $output, $this->dest );

			return $result;
		}

		$this->dataWriter = new AnalyzerDirectDataWriter( $workspaceDB );

		$this->executionTime = new ExecutionTime();
		try {
			$result = parent::execute( $input, $output );
			$this->logExecutionTime( $output, $this->dest );
			return $result;
		} finally {
			$this->dataWriter = null;
		}
	}

	/**
	 * @param SplFileInfo $file
	 *
	 * @return bool
	 * @throws Throwable
	 */
	protected function processFile( SplFileInfo $file ): bool {
		$this->output->writeln( "Analyzing file '{$this->currentFile->getFilename()}'" );

		$analyzer = new ConfluenceAnalyzer(
			$this->dataWriter,
			$this->output,
			$this->getMigrationConfig(),
			$this->wikisConfig
		);

		$analyzer->analyze( $file );

		return true;
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
	 * @return MigrationConfig
	 */
	private function getMigrationConfig(): MigrationConfig {
		$filename = $this->input->getOption( 'config' );

		$advancedConfig = [];
		if ( !empty( $filename ) ) {
			$configOptionHelper = new ConfigOptionHelper( $filename );
			$validationError = $configOptionHelper->validateFile();

			if ( $validationError !== null ) {
				$this->output->writeln( $validationError );
				exit( 1 );
			} else {
				$advancedConfig = $configOptionHelper->getConfig();
				$this->output->writeln( 'Config file loaded successfully' );
			}
		}

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
			$csvParser = new CSVParser(
				$filename,
				static function ( array $data, int $rowNumber ): ?array {
					if ( $rowNumber === 0 && preg_match( '/^confluence.space.key$/i', $data[0] ?? '' ) ) {
						// Skip the header line
						return null;
					}

					// phpcs:ignore Squiz.PHP.InnerFunctions.NotAllowed
					function sanitize( string $text ): string {
						return preg_replace( '#_+#', '_',
							str_replace(
								[ ' ', ':', ';', ',', '#', '+', '?', '*', '~', '"', "'" ],
								'_',
								trim( $text )
							) );
					}

					$record = [
						'space-key' => trim( $data[0] ?? '' ),
						'wiki-name' => sanitize( $data[1] ?? '' ),
						'wiki-namespace' => sanitize( $data[2] ?? '' ),
						'wiki-root-page' => trim( $data[3] ?? '' ),
					];
					if ( empty( $record['space-key'] ) ) {
						throw new \Exception( "Space key must not be empty (row $rowNumber)" );
					}
					if ( $record['wiki-namespace'] && preg_match( '#^\d#', $record['wiki-namespace'] ) ) {
						throw new \Exception(
							"Wiki namespace must not start with a digit: " .
							"'{$record['wiki-namespace']}' (row $rowNumber)" );
					}
					return $record;
				},
				$this->getMigrationConfig()
			);
			$error = '';
			$isValid = $csvParser->validateFile( $error );
			if ( !$isValid ) {
				$this->output->writeln( $error );
				exit( 1 );
			}

			foreach ( $csvParser->getRecords() as $wikiConfig ) {
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
	 * @param WorkspaceDB $workspaceDB
	 *
	 * @return void
	 */
	private function readUsermapFile( WorkspaceDB $workspaceDB ): void {
		$filename = $this->input->getOption( 'usermap' );
		if ( !empty( $filename ) ) {
			$csvParser = new CSVParser(
				$filename,
				static function ( array $data, int $rowNumber ): ?array {
					$confluenceUsername = trim( $data[0] ?? '' );
					if ( $rowNumber === 0 && preg_match( '/^confluence.username$/i', $confluenceUsername ) ) {
						// Skip the header line
						return null;
					}

					// Confluence usernames are matched case-insensitively against
					// the `lowerName` property from entities.xml.
					return [
						'confluence-username' => strtolower( $confluenceUsername ),
						'wiki-username' => trim( $data[1] ?? '' ),
					];
				},
				$this->getMigrationConfig()
			);
			$error = '';
			$isValid = $csvParser->validateFile( $error );
			if ( !$isValid ) {
				$this->output->writeln( $error );
				exit( 1 );
			}

			foreach ( $csvParser->getRecords() as $record ) {
				// The real user_key is not known yet at this point (it is only
				// present in entities.xml); use the confluence username as a
				// placeholder key, matched and adopted later in
				// WorkspaceDB::addUser() once the real user_key is known.
				$workspaceDB->addUser(
					$record['confluence-username'],
					$record['wiki-username'],
					'',
					[],
					$record['confluence-username']
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
