<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Convert as CommandConvert;
use HalloWelt\MediaWiki\Lib\Migration\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\IConverter;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterPipeDataWriter;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Database\DataWriter\PipeChannel;
use HalloWelt\MigrateConfluence\Database\DataWriter\WorkerPool;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\Version;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Convert extends CommandConvert {

	/** @var string */
	private string $wikiTextBasePath = '';

	/** @var IConverterDataWriter|null */
	private ?IConverterDataWriter $dataWriter;

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
	 *
	 * @return int
	 * @throws Exception
	 */
	protected function execute( InputInterface $input, OutputInterface $output ): int {
		$workers = (int)$input->getOption( 'workers' );
		$isChildProcess = $input->hasParameterOption( '--worker' );

		$this->dest = realpath( $input->getOption( 'dest' ) );
		if ( !is_dir( $this->dest ) ) {
			$output->writeln( "Destination does not exist" );
			return Command::FAILURE;
		}

		if ( $isChildProcess ) {
			$this->dataWriter = new ConverterPipeDataWriter( new PipeChannel() );

			try {
				return parent::execute( $input, $output );
			} finally {
				$this->dataWriter = null;
			}
		}

		$workspaceDB = WorkspaceDB::open( $this->dest, true );
		$workspaceDB->addLogEntry(
			'info',
			'convert',
			__CLASS__,
			sprintf( '[%s] use version %s', date( 'c' ), Version::getVersion() )
		);

		if ( $workers > 1 ) {
			$pool = new WorkerPool( $output, new ConverterDirectDataWriter( $workspaceDB ) );

			$this->executionTime = new ExecutionTime();
			$result = $pool->run( WorkerPool::baseCommandFromArgv(), $workers );
			$this->logExecutionTime();

			return $result;
		}

		$this->dataWriter = new ConverterDirectDataWriter( $workspaceDB );

		try {
			return parent::execute( $input, $output );
		} finally {
			$this->dataWriter = null;
		}
	}

	/**
	 * @throws Exception
	 */
	protected function doProcessFile(): bool {
		if ( !$this->dataWriter ) {
			throw new Exception( 'No data writer is set' );
		}

		$converterFactoryCallbacks = $this->config['converters'];

		$this->wikiTextBasePath = $this->dest . '/content/wikitext';
		$this->makeTargetPathname();
		$this->ensureTargetPath();

		$this->readConfigFile( $this->config );

		foreach ( $converterFactoryCallbacks as $key => $callback ) {
			$converter = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace, $this->dataWriter ]
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

}
