<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\ComposerDeploymentInfo;
use HalloWelt\MigrateConfluence\Utility\ComposerSkipHelper;
use HalloWelt\MigrateConfluence\Utility\DBComposerDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use Symfony\Component\Console\Output\Output;

class BlogPosts extends ContentProcessorBase {

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
		$wikiTitles = $this->collectBySpaceIdsReplaceByKey(
			fn ( int $spaceId ): array => $this->dataLookup->getBlogPostIdWikiBlogPostTitleMap( $spaceId ),
			fn (): array => $this->dataLookup->getBlogPostIdWikiBlogPostTitleMap()
		);

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
