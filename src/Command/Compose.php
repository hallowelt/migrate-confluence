<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Compose extends CommandCompose {

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
	 * @return Compose
	 */
	public static function factory( $config ): Compose {
		return new static( $config );
	}

	/**
	 * @return bool
	 */
	protected function processFiles() {
		$this->readConfigFile( $this->config );
		parent::processFiles();
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
