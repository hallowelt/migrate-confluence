<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class BlogPosts extends ProcessorBase {

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
		return 'blogs';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addBlogPages();

		$this->writeOutputFile();
	}

	private function addBlogPages(): void {
		// Get all blog post titles for a certain spacce id from DB and add them as pages to the workspace
		$wikiTitles = $this->dataLookup->getBlogPostIdWikiBlogPostTitleMap( $this->currentSpaceId );

		foreach ( $wikiTitles as $blogPostId => $blogPostTitle ) {
			if ( $this->skipHelper->skipBlogPost( $blogPostTitle ) ) {
				$this->output->writeln( "Skip blog post $blogPostTitle." );
				$this->deploymentInfo->addSkippedPage( $blogPostTitle );
				continue;
			}
			$this->output->writeln( "Processing blog post '$blogPostTitle' ..." );

			$namespace = $this->getNamespace( $blogPostTitle );

			$revisions = $this->dataLookup->getBlogPostRevisionsForBlogPostId( $blogPostId );
			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$bodyContentIds = json_decode( $revision['body_content_ids'], true );
				if ( !is_array( $bodyContentIds ) ) {
					continue;
				}

				$pageContent = '';
				foreach ( $bodyContentIds as $bodyContentId ) {
					if ( empty( $bodyContentId ) ) {
						// Skip if no reference to a body content is not set
						continue;
					}
					$this->output->writeln( "Getting '$bodyContentId' body content..." );
					$pageContent .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
				}

				$this->addRevision(
					$blogPostTitle,
					$pageContent,
					$timestamp,
					'',
					'blog_post',
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}
	}
}
