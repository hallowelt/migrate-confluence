<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class Pages extends ContentProcessorBase {

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
		return 'pages';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addContentPages();

		$this->writeOutputFile();
	}

	private function addContentPages(): void {
		$wikiTitles = $this->collectBySpaceIdsReplaceByKey(
			fn ( int $spaceId ): array => $this->dataLookup->getPageIdWikiPageTitleMap( $spaceId ),
			fn (): array => $this->dataLookup->getPageIdWikiPageTitleMap()
		);

		foreach ( $wikiTitles as $pageId => $pageTitle ) {
			if ( $this->skipHelper->skipPage( $pageTitle ) ) {
				$this->output->writeln( "Skip page $pageTitle." );
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}
			$this->output->writeln( "Processing page '$pageTitle' ..." );

			$namespace = $this->getNamespace( $pageTitle );

			$spaceId = $this->dataLookup->getSpaceIdForPageId( $pageId );
			$spaceDescriptions = $this->dataLookup->getSpaceDescriptionRevisionsForSpaceId( $spaceId );
			$homepageId = $this->dataLookup->getSpaceHomepageIdForSpaceId( $spaceId );

			if ( $pageId === $homepageId ) {
				$this->output->writeln(
					"Page '$pageTitle' is a homepage, adding space description to page content if applicable..."
				);
				$revisions = $this->dataLookup->getPageRevisionsForPageId( $homepageId );
			} else {
				$revisions = $this->dataLookup->getPageRevisionsForPageId( $pageId );
			}

			foreach ( $revisions as $revision ) {
				$timestamp = (string)$revision['revision_timestamp'];
				if ( !$this->hasValidContentIdsJson( (string)( $revision['body_content_ids'] ?? '' ) ) ) {
					continue;
				}
				$pageContent = $this->buildConvertedContentFromIdsJson(
					$this->workspace,
					(string)( $revision['body_content_ids'] ?? '' ),
					'body content',
					'',
					true
				);

				if ( $homepageId !== null ) {
					$pageContent .= $this->addSpaceDescriptionToMainPage(
						$pageId,
						$homepageId,
						$timestamp,
						$spaceDescriptions
					);
				}

				$this->addRevision(
					$pageTitle,
					$pageContent,
					$timestamp
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}
	}
}
