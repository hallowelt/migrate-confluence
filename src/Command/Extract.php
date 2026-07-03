<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Extract as CommandExtract;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IExtractor;
use HalloWelt\MediaWiki\Lib\Migration\IFileProcessorEventHandler;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use HalloWelt\MigrateConfluence\Utility\WikiConfigCSVParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Extract extends CommandExtract {

	/**
	 * @var IExtractor[]
	 */
	protected $extractors = [];

	/**
	 * @var array
	 */
	protected $eventhandlers = [];

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
				'wikiconf',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the csv file containing interwiki configuration'
			)
		);
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
	 * @return void
	 */
	protected function beforeProcessFiles() {
		$this->readConfigFile( $this->config );
		parent::beforeProcessFiles();
		// Explicitly reset the persisted data
		$this->buckets = new DataBuckets( $this->getBucketKeys() );

		if ( !isset( $this->config['extractors'] ) ) {
			throw new Exception( "No 'extractors' key in config" );
		}

		$extractorFactoryCallbacks = $this->config['extractors'];
		foreach ( $extractorFactoryCallbacks as $key => $callback ) {
			$extractor = call_user_func_array(
				$callback,
				[ $this->config, $this->workspace, $this->buckets ]
			);
			if ( $extractor instanceof IExtractor === false ) {
				throw new Exception(
					"Factory callback for extractor '$key' did not return an "
					. "IExtractor object"
				);
			}
			if ( $extractor instanceof IOutputAwareInterface ) {
				$extractor->setOutput( $this->output );
			}
			if ( $extractor instanceof IDestinationPathAware ) {
				$extractor->setDestinationPath( $this->dest );
			}
			$this->extractors[$key] = $extractor;
			if ( $extractor instanceof IFileProcessorEventHandler ) {
				$this->eventhandlers[$key] = $extractor;
			}
		}
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
				$advancedConfig = $configOptionHelper->getConfig();
				$config = array_merge( $config, $advancedConfig );
				$this->output->writeln( 'Config file loaded successfully' );
			}
		}
	}

	/**
	 *
	 * @inheritDoc
	 */
	protected function getBucketKeys(): array {
		return [];
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
