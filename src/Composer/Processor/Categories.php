<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\Hooks\ComposerHooks;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\Sanitizer;
use Symfony\Component\Console\Output\Output;

/**
 * Creates one category page for every category that the converted content of a wiki uses.
 */
class Categories extends ProcessorBase {

	/**
	 * @param Builder $builder
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param DBComposerDataLookup $dataLookup
	 */
	public function __construct(
		protected Builder $builder,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
		protected DBComposerDataLookup $dataLookup,
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
	}

	/** @inheritDoc */
	protected function getOutputName(): string {
		return 'categories';
	}

	/** @inheritDoc */
	public function execute(): void {
		// Different labels can sanitize to the same category, so group them by category name.
		$categories = [];
		foreach ( $this->dataLookup->getCategoryLabelsForSpaceIds( $this->currentSpaceIds ?? [] ) as $label ) {
			$name = Sanitizer::sanitizeCategory( $label );
			if ( $name !== '' ) {
				$categories[$name][] = $label;
			}
		}

		if ( $categories === [] ) {
			return;
		}
		ksort( $categories );

		foreach ( $categories as $name => $labels ) {
			$title = "Category:$name";
			$content = ComposerHooks::composeCategoryContent( "Pages with the label $name", [
				'category' => $name,
				'title' => $title,
				'labels' => $labels,
			] );
			$this->addRevision( $title, $content );
		}

		$this->writeOutputFile();
	}
}
