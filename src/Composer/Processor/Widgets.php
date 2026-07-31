<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class Widgets extends ProcessorBase {

	/** @var DBComposerDataLookup */
	private DBComposerDataLookup $dataLookup;

	/**
	 * @param DBComposerDataLookup $dataLookup
	 * @param Builder $builder
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		DBComposerDataLookup $dataLookup,
		Builder $builder,
		Output $output,
		string $dest,
		MigrationConfig $migrationConfig
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
		$this->dataLookup = $dataLookup;
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'widgets';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$requiredWidgets = $this->dataLookup->getRequiredWidgets();

		if ( empty( $requiredWidgets ) ) {
			return;
		}

		$basePath = dirname( __DIR__ ) . '/_widgets/';

		foreach ( $requiredWidgets as $widgetName ) {
			$filePath = $basePath . $widgetName;
			if ( !is_file( $filePath ) ) {
				$this->output->writeln( "Warning: no default file found for widget '$widgetName', skipping." );
				continue;
			}
			$this->addRevision( "Widget:$widgetName", file_get_contents( $filePath ) );
		}

		$this->writeOutputFile();
	}
}
