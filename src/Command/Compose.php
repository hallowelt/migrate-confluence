<?php

namespace HalloWelt\MigrateConfluence\Command;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use SplFileInfo;
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
		$this->ensureTargetDirs();
		$this->workspace = new Workspace( new SplFileInfo( $this->src ) );

		$this->initExecutionTime();

		$this->buckets = new DataBuckets( $this->getBucketKeys() );
		$this->buckets->loadFromWorkspace( $this->workspace );
		$composers = $this->makeComposers();
		$mediawikixmlbuilder = new Builder();
		foreach ( $composers as $composer ) {
			$composer->setDestinationPath( $this->dest );
			$composer->buildXML( $mediawikixmlbuilder );

		}

		$this->logExecutionTime();
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
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-space-description-id-to-body-id-map',
			'global-body-contents-to-pages-map',
			'global-title-attachments',
			'global-title-revisions',
			'global-files',
			'global-additional-files'
		];
	}

	/**
	 * ToDo: Set this method in composer to protected
	 *
	 * @return void
	 */
	private function ensureTargetDirs() {
		$path = "{$this->dest}/result/images";
		if ( !file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}
}
