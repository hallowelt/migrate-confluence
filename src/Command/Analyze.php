<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\Migration\Command\Analyze as CommandAnalyze;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class Analyze extends CommandAnalyze {

	/**
	 * @inheritDoc
	 */
	protected function configure(): void {
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
	 * @return Analyze
	 */
	public static function factory( $config ): Analyze {
		return new static( $config );
	}

	/**
	 * @return bool
	 */
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

	/**
	 *
	 * @inheritDoc
	 */
	protected function getBucketKeys() {
		return [
			'global-files',
			'global-title-attachments',
			'global-title-revisions',
			'global-space-id-to-prefix-map',
			'global-space-key-to-prefix-map',
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-space-description-id-to-body-id-map',
			'global-space-details',
			'global-userkey-to-username-map',
			'global-pages-titles-map',
			'global-page-id-to-title-map',
			'global-page-id-to-space-id',
			'global-body-contents-to-pages-map',
			'global-additional-files',
			'global-attachment-orig-filename-target-filename-map',
			'global-filenames-to-filetitles-map',
		];
	}
}
