<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\Migration\Command\Extract as CommandExtract;
use HalloWelt\MigrateConfluence\Utility\ExecutionTime;
use HalloWelt\MediaWiki\Lib\Migration\IExtractor;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Extract extends CommandExtract {

	/**
	 *
	 * @var IExtractor[]
	 */
	protected $extractors = [];

	/**
	 *
	 * @inheritDoc
	 */
	protected function configure() {
		$config = parent::configure();

		/** @var InputDefinition */
		$definition = $this->getDefinition();
		$definition->addOption(
			new InputOption(
				'config',
				null,
				InputOption::VALUE_REQUIRED,
				'Specifies the path to the config yaml file'
			)
		);

		return $config;
	}

	/**
	 * @param array $config
	 * @return Extract
	 */
	public static function factory( $config ): Extract {
		return new static( $config );
	}

	protected function beforeProcessFiles() {
		$this->readConfigFile( $this->config );
		parent::beforeProcessFiles();
	}

	protected function processFiles() {
		$executionTime = new ExecutionTime();

		$returnValue = parent::processFiles();

		$executionTime = $executionTime->getHumanReadableExecutionTime();
		$this->output->writeln( $executionTime );

		return $returnValue;
	}

	/**
	 * @param array &$config
	 * @return void
	 */
	private function readConfigFile( &$config ): void {
		$filename = $this->input->getOption( 'config' );
		if ( is_file( $filename ) ) {
			$content = file_get_contents( $filename );
			if ( $content ) {
				try {
					$yaml = Yaml::parse( $content );
					$config = array_merge( $config, $yaml );
				} catch ( ParseException $e ) {
					$this->output->writeln( 'Invalid config file provided' );
					exit( true );
				}
			}
		}
	}
}
