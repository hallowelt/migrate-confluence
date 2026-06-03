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
		// Key is pageId, value is pageTitle - do not use array_merge at this point to avoid renumbering of keys
		$wikiTitles = $this->dataLookup->getBlogPostIdTargetBlogPostTitleMap();

		foreach ( $wikiTitles as $pageId => $pageTitle ) {
			$this->output->writeln( "Processing page '$pageTitle'..." );

			if ( $this->skipTitleByConfig( $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			} elseif ( $this->skipBlogPostId( $pageId, $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}

			$namespace = $this->getNamespace( $pageTitle );

			$revisions = $this->dataLookup->getBlogPostRevisionsForBlogPostId( $pageId );

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
					$pageTitle,
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
