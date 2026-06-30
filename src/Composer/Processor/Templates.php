<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class Templates extends ProcessorBase {

	/**
	 * @param Builder $builder
	 * @param DBComposerDataLookup $dataLookup
	 * @param Workspace $workspace
	 * @param Output $output
	 * @param string $dest
	 * @param MigrationConfig $migrationConfig
	 * @param ComposerDeploymentInfo $deploymentInfo
	 * @param ComposerSkipHelper $skipHelper
	 */
	public function __construct(
		protected Builder $builder,
		protected DBComposerDataLookup $dataLookup,
		protected Workspace $workspace,
		protected Output $output,
		protected string $dest,
		protected MigrationConfig $migrationConfig,
		protected ComposerDeploymentInfo $deploymentInfo,
		protected ComposerSkipHelper $skipHelper
	) {
		parent::__construct( $builder, $output, $dest, $migrationConfig );
	}

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'templates';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$wikiTitles = [];
		if ( is_array( $this->currentSpaceIds ) ) {
			foreach ( $this->currentSpaceIds as $spaceId ) {
				// Merge blog post titles for each space ID into the $wikiTitles array
				// Use array_replace to ensure that if there are duplicate blog post IDs,
				// the last one will overwrite the previous ones.
				$wikiTitles = array_replace(
					$wikiTitles,
					$this->dataLookup->getPageTemplateIdWikiTitleMap( (int)$spaceId )
				);
			}
		} else {
			$wikiTitles = $this->dataLookup->getPageTemplateIdWikiTitleMap();
		}

		foreach ( $wikiTitles as $templateId => $pageTitle ) {
			if ( $this->skipHelper->skipTemplate( $pageTitle ) ) {
				$this->output->writeln( "Skip template '$pageTitle'" );
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}
			$this->output->writeln( "Processing template '$pageTitle' ..." );

			$namespace = $this->getNamespace( $pageTitle );

			$revisions = $this->dataLookup->getPageTemplateRevisionsForTemplateId( $templateId );

			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$templateContentIds = json_decode( $revision['template_content_ids'], true );
				if ( !is_array( $templateContentIds ) ) {
					continue;
				}

				$pageContent = '';
				foreach ( $templateContentIds as $templateContentId ) {
					if ( empty( $templateContentId ) ) {
						// Skip if no reference to a body content is not set
						continue;
					}

					$this->output->writeln( "Getting '$templateContentId' template content..." );
					$pageContent .= $this->workspace->getConvertedContent( 'pt_' . $templateContentId ) . "\n";
				}

				$this->addRevision(
					$pageTitle,
					$pageContent,
					$timestamp,
					''
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}

		$this->writeOutputFile();
	}
}
