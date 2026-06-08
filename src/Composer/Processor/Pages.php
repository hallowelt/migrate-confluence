<?php

namespace HalloWelt\MigrateConfluence\Composer\Processor;

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
		$this->addContentPages();

		$this->writeOutputFile();
	}

	private function addContentPages(): void {
		// Get all page titles from DB and add them as pages to the workspace
		$wikiTitles = $this->dataLookup->getPageIdWikiPageTitleMap();

		foreach ( $wikiTitles as $pageId => $pageTitle ) {
			$this->output->writeln( "Processing page '$pageTitle'..." );

			if ( $this->skipTitleByConfig( $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			} elseif ( $this->skipPageId( $pageId, $pageTitle ) ) {
				$this->deploymentInfo->addSkippedPage( $pageTitle );
				continue;
			}

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
}
