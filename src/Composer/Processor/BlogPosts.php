<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

class BlogPosts extends ProcessorBase {

	/**
	 * @return string
	 */
	protected function getOutputName(): string {
		return 'blog';
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$this->addBlogPages();

		$this->writeOutputFile();
	}

	private function addBlogPages(): void {
		// Get all page titles from DB and add them as pages to the workspace
		// Key is blogPostId, value is blogPostTitle - do not use array_merge at this point to avoid renumbering of keys
		$wikiTitles = $this->dataLookup->getBlogPostIdWikiBlogPostTitleMap();

		foreach ( $wikiTitles as $blogPostId => $blogPostTitle ) {
			if ( $this->skipHelper->skipBlogPostById( $blogPostId ) ) {
				$this->output->writeln( "Skip page $blogPostTitle." );
				$this->deploymentInfo->addSkippedPage( $blogPostTitle );
				continue;
			}
			$this->output->writeln( "Processing page '$blogPostTitle' ..." );

			$namespace = $this->getNamespace( $blogPostTitle );

			$revisions = $this->dataLookup->getBlogPostRevisionsForBlogPostId( $blogPostId );
			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$bodyContentIds = json_decode( $revision['body_content_ids'], true );

				$pageContent = '';
				foreach ( $bodyContentIds as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
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
