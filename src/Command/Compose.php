<?php

namespace HalloWelt\MigrateConfluence\Command;

use Exception;
use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Command\Compose as CommandCompose;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\IDestinationPathAware;
use HalloWelt\MigrateConfluence\Utility\ConfigOptionHelper;
use SplFileInfo;
use Symfony\Component\Console\Input\InputOption;

class Compose extends CommandCompose {

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
	 * @return int
	 * @throws Exception
	 */
	protected function processFiles(): int {
		$this->readConfigFile( $this->config );
		$this->ensureTargetDirs();
		$this->workspace = new Workspace( new SplFileInfo( $this->dest ) );

		$this->initExecutionTime();

		$this->buckets = new DataBuckets( $this->getBucketKeys() );
		$this->buckets->loadFromWorkspace( $this->workspace );
		$composers = $this->makeComposers();
		$mediawikixmlbuilder = new Builder();
		foreach ( $composers as $composer ) {
			if ( $composer instanceof IDestinationPathAware ) {
				$composer->setDestinationPath( $this->dest );
			}
			$composer->buildXML( $mediawikixmlbuilder );
		}

		$this->logExecutionTime();

		return 0;
	}

	/**
	 * @param array &$config
	 *
	 * @return void
	 */
	private function readConfigFile( array &$config ): void {
		$filename = $this->input->getOption( 'config' );

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

	/**
	 *
	 * @inheritDoc
	 */
	protected function getBucketKeys(): array {
		return [];
	}

	/**
	 * ToDo: Set this method in composer to protected
	 *
	 * @return void
	 */
	private function ensureTargetDirs(): void {
		$path = "$this->dest/result/images";
		if ( !file_exists( $path ) ) {
			mkdir( $path, 0755, true );
		}
	}
}
