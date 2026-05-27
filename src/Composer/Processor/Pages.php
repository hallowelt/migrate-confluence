<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Pages extends ProcessorBase {

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
		$this->addDefaultPages();
		$this->addContentPages();
		$this->addTemplatePages();

		$this->writeOutputFile();
	}

	private function addTemplatePages(): void {
		$wikiTitles = $this->dataLookup->getPageTemplateIdTargetTitleMap();

		foreach ( $wikiTitles as $templateId => $pageTitle ) {
			$this->output->writeln( "Processing template '$pageTitle'..." );

			if ( $this->skipTitle( $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}

			$namespace = $this->getNamespace( $pageTitle );

			$revisions = $this->dataLookup->getPageTemplateRevisionsForTemplateId( $templateId );

			foreach ( $revisions as $revision ) {
				$timestamp = $revision['revision_timestamp'];
				$bodyContentIds = json_decode( $revision['body_content_ids'], true );

				$pageContent = '';
				foreach ( $bodyContentIds as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
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
					$this->getContentModel( $pageTitle )
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}
	}

	private function addContentPages(): void {
		/** Add content pages */

		// Get all page titles from DB and add them as pages to the workspace
		// Key is pageId, value is pageTitle - do not use array_merge at this point to avoid renumbering of keys
		$wikiTitles = $this->dataLookup->getPageIdTargetPageTitleMap()
			+ $this->dataLookup->getBlogPostIdTargetBlogPostTitleMap();

		foreach ( $wikiTitles as $pageId => $pageTitle ) {
			$this->output->writeln( "Processing page '$pageTitle'..." );

			if ( $this->skipTitle( $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}

			$namespace = $this->getNamespace( $pageTitle );

			$spaceId = $this->dataLookup->getSpaceIdForPageId( $pageId );
			$isBlogPost = $this->isBlogPost( $namespace );

			if ( $isBlogPost ) {
				$revisions = $this->dataLookup->getBlogPostRevisionsForPageId( $pageId );
				$spaceDescriptions = [];
				$homepageId = -1;
			} else {
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
			}

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

				$pageContent .= $this->addSpaceDescriptionToMainPage(
					$pageId,
					$homepageId,
					$timestamp,
					$spaceDescriptions
				);

				$this->addRevision(
					$pageTitle,
					$pageContent,
					$timestamp,
					'',
					$this->getContentModel( $pageTitle )
				);
			}

			$this->deploymentInfo->addNamespace( $namespace );
		}
	}

	/**
	 * @param string $pageTitle
	 * @return string
	 */
	private function getContentModel( string $pageTitle ): string {
		if ( strpos( $pageTitle, 'Blog:' ) === 0 ) {
			return 'blog_post';
		}

		return '';
	}

	/**
	 * @return void
	 */
	private function addDefaultPages(): void {
		$basepath = dirname( __DIR__ ) . '/_defaultpages/';

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}

			$file = $fileObj->getPathname();
			$namespacePrefix = basename( dirname( $file ) );
			$pageName = basename( $file );
			$wikiPageName = "$namespacePrefix:$pageName";
			$wikiText = file_get_contents( $file );

			$this->addRevision( $wikiPageName, $wikiText );
		}
	}

	/**
	 * Add space description to homepage
	 *
	 * @param int $pageId
	 * @param int $homepageId
	 * @param string $pageRevisionTimestamp
	 * @param array $spaceDescriptionRevisions
	 *
	 * @return string
	 */
	private function addSpaceDescriptionToMainPage(
		int $pageId,
		int $homepageId,
		string $pageRevisionTimestamp,
		array $spaceDescriptionRevisions
	): string {
		if ( $pageId !== $homepageId ) {
			return '';
		}

		foreach ( $spaceDescriptionRevisions as $spaceDescriptionRevision ) {
			if ( !isset( $spaceDescriptionRevision['revision_timestamp'] ) ) {
				continue;
			}

			$spaceDescriptionTimestamp = (string)$spaceDescriptionRevision['revision_timestamp'];
			if ( $spaceDescriptionTimestamp > $pageRevisionTimestamp ) {
				continue;
			}

			$bodyContentIds = json_decode( $spaceDescriptionRevision['body_content_ids'], true );
			if ( !is_array( $bodyContentIds ) ) {
				continue;
			}

			$description = '';
			foreach ( $bodyContentIds as $bodyContentId ) {
				if ( $bodyContentId === '' ) {
					continue;
				}

				$description .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
			}

			if ( $description !== '' ) {
				return $this->wrapSpaceDescription( $description );
			}
		}

		return '';
	}

	/**
	 * @param string $description
	 * @return string
	 */
	private function wrapSpaceDescription( string $description ): string {
		$strippedDescription = trim( preg_replace( '/<!-- From bodyContent .*?-->/s', '', (string)$description ) );
		if ( $strippedDescription === '' ) {
			return '';
		}
		return '<div class="space-description">' . $description . '</div>';
	}

	/**
	 * @param string $namespace
	 * @return bool
	 */
	private function isBlogPost( string $namespace ): bool {
		if ( $namespace === 'Blog' ) {
			return true;
		}
		return false;
	}
}
