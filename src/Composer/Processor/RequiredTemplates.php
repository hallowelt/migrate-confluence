<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class RequiredTemplates extends ProcessorBase {

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
		return 'required_templates';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$requiredTemplates = $this->dataLookup->getRequiredTemplates();

		if ( empty( $requiredTemplates ) ) {
			return;
		}

		$basePath = dirname( __DIR__ ) . '/_templates/';

		foreach ( $requiredTemplates as $templateName ) {
			$filePath = $basePath . $templateName;
			if ( !is_file( $filePath ) ) {
				$this->output->writeln( "Warning: no default file found for template '$templateName', skipping." );
				continue;
			}
			$this->addRevision( "Template:$templateName", file_get_contents( $filePath ) );
		}

		$this->writeOutputFile();
	}
}
