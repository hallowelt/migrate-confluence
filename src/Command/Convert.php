<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\Migration\Command\Convert as CommandConvert;
use HalloWelt\MigrateConfluence\Utility\ExecutionTime;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Convert extends CommandConvert {

	/**
	 *
	 * @var string
	 */
	private $targetBasePath = '';

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
	 * @return Convert
	 */
	public static function factory( $config ): Convert {
		return new static( $config );
	}

	protected function processFiles() {
		$executionTime = new ExecutionTime();

		$returnValue = parent::processFiles();

		$executionTime = $executionTime->getHumanReadableExecutionTime();
		$this->output->writeln( $executionTime );

		return $returnValue;
	}

	protected function doProcessFile(): bool {
		$this->readConfigFile( $this->config );
		return parent::doProcessFile();
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
