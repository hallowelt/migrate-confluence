<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\Migration\Command\Extract as CommandExtract;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\IExtractor;
use HalloWelt\MediaWiki\Lib\Migration\IFileProcessorEventHandler;
use HalloWelt\MediaWiki\Lib\Migration\IOutputAwareInterface;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
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

		$wikiConfig = [];
		$filename = $this->input->getOption( 'wikiconf' );
		if ( is_string( $filename ) && is_file( realpath( $filename ) ) ) {
			$resolvedFilename = realpath( $filename );
			$handle = $resolvedFilename ? fopen( $resolvedFilename, 'r' ) : false;
			if ( $handle !== false ) {
				while ( ( $data = fgetcsv( $handle, 0, ';' ) ) !== false ) {
					if ( $data === [ null ] ) {
						continue;
					}
					$data = array_map( 'trim', $data );

					// Skip header row: confluence-space-key;wiki-name;wiki-namespace;wiki-root-page-prefix
					if ( isset( $data[0] ) && $data[0] === 'confluence-space-key' ) {
						continue;
					}

					if ( !isset( $data[0] ) || !isset( $data[1] )
						|| !isset( $data[2] ) || !isset( $data[3] )
						|| $data[0] === ''
					) {
						continue;
					}

					$wikiConfig[] = [
						'space-key' => $data[0],
						'wiki-name' => $data[1],
						'wiki-namespace' => $data[2],
						'wiki-root-page' => $data[3],
					];
				}
				fclose( $handle );
			}
		}
		$config['wiki-config'] = $wikiConfig;
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
