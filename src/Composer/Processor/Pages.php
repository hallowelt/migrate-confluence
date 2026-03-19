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

		$this->writeOutputFile();
	}

	private function addContentPages(): void {
		/** Add content pages */
		$spaceIdHomepagesMap = $this->buckets->getBucketData(
			'global-space-id-homepages'
		);
		$homepagespaceIdMap = array_flip( $spaceIdHomepagesMap );
		$spaceIdDescriptionIdMap = $this->buckets->getBucketData(
			'global-space-id-to-description-id-map'
		);
		$spaceBodyIdDescriptionIdBodyIDMap = $this->buckets->getBucketData(
			'global-body-content-id-to-space-description-id-map'
		);
		$titleRevisions = $this->buckets->getBucketData(
			'global-title-revisions'
		);

		/** Prepare required maps */
		$bodyContentIdMainpageId = $this->buildMainpageContentMap( $spaceIdHomepagesMap );

		/** Add grouped pages */
		foreach ( $titleRevisions as $pageTitle => $pageRevisions ) {
			if ( $this->skipTitle( $pageTitle ) ) {
				continue;
			}

			$sortedRevisions = $this->sortRevisions( $pageRevisions );
			foreach ( $sortedRevisions as $timestamp => $bodyContentIds ) {
				$bodyContentIdsArr = explode( '/', $bodyContentIds );
				$pageContent = "";
				foreach ( $bodyContentIdsArr as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
						// Skip if no reference to a body content is not set
						continue;
					}
					$this->output->writeln( "Getting '$bodyContentId' body content..." );
					$pageContent .= $this->workspace->getConvertedContent( $bodyContentId ) . "\n";
					$pageContent .= $this->addSpaceDescriptionToMainPage(
						$bodyContentId,
						$bodyContentIdMainpageId,
						$homepagespaceIdMap,
						$spaceIdDescriptionIdMap,
						array_flip( $spaceBodyIdDescriptionIdBodyIDMap )
					);
				}

				$this->addRevision(
					$pageTitle,
					$pageContent,
					$timestamp,
					'',
					$this->getContentModel( $pageTitle )
				);
			}
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
	 * @param array $spaceIdHomepagesMap
	 * @return array
	 */
	private function buildMainpageContentMap( array $spaceIdHomepagesMap ): array {
		$bodyContentsToPagesMap = $this->buckets->getBucketData(
			'global-body-content-id-to-page-id-map'
		);

		$bodyContentIdMainpageId = [];
		$pagesToBodyContents = array_flip( $bodyContentsToPagesMap );
		foreach ( $spaceIdHomepagesMap as $homepageId ) {
			if ( !isset( $pagesToBodyContents[$homepageId] ) ) {
				continue;
			}
			$bodyContentsID = $pagesToBodyContents[$homepageId];
			$bodyContentIdMainpageId[$bodyContentsID] = $homepageId;
		}

		return $bodyContentIdMainpageId;
	}

	/**
	 * @param array $pageRevisions
	 * @return array
	 */
	private function sortRevisions( array $pageRevisions ): array {
		$sortedRevisions = [];
		foreach ( $pageRevisions as $pageRevision ) {
			$pageRevisionData = explode( '@', $pageRevision );
			$bodyContentIds = $pageRevisionData[0];

			$versionTimestamp = explode( '-', $pageRevisionData[1] );
			// $version = $versionTimestamp[0];
			$timestamp = $versionTimestamp[1];

			$sortedRevisions[$bodyContentIds] = $timestamp;
		}

		// Sorting revisions with timestamps
		natsort( $sortedRevisions );
		$sortedRevisions = array_flip( $sortedRevisions );

		// Using history revisions?
		if ( !$this->includeHistory() ) {
			$bodyContentIds = end( $sortedRevisions );
			$timestamp = array_search( $bodyContentIds, $sortedRevisions );
			// Reset sortedRevisions
			$sortedRevisions = [];
			$sortedRevisions[$timestamp] = $bodyContentIds;
		}

		return $sortedRevisions;
	}

	/**
	 * Add space description to homepage
	 *
	 * @param string|int $bodyContentId
	 * @param array $bodyContentIdMainpageId
	 * @param array $homepagespaceIdMap
	 * @param array $spaceIdDescriptionIdMap
	 * @param array $spaceDescriptionIdBodyIdMap
	 * @return string
	 */
	private function addSpaceDescriptionToMainPage(
		$bodyContentId, array $bodyContentIdMainpageId,
		array $homepagespaceIdMap, array $spaceIdDescriptionIdMap,
		array $spaceDescriptionIdBodyIdMap
	): string {
		$pageContent = '';

		if ( isset( $bodyContentIdMainpageId[$bodyContentId] ) ) {
			// get homepage id if it is a homepage
			$mainpageID = $bodyContentIdMainpageId[$bodyContentId];
			if ( isset( $homepagespaceIdMap[$mainpageID] ) ) {
				// get space id
				$spaceId = $homepagespaceIdMap[$mainpageID];
				if ( isset( $spaceIdDescriptionIdMap[$spaceId] ) ) {
					// get description id
					$descId = $spaceIdDescriptionIdMap[$spaceId];
					if ( isset( $spaceDescriptionIdBodyIdMap[$descId] ) ) {
						// get description id
						$descBodyId = $spaceDescriptionIdBodyIdMap[$descId];
						$description = $this->workspace->getConvertedContent( $descBodyId );
						if ( $description !== '' ) {
							$pageContent .= "[[Space description::$description]]\n";
						}
					}
				}
			}
		}

		return $pageContent;
	}
}
