<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class InterwikiDbMock {
	private int $nextPageId = 1000;

	private int $nextBodyContentId = 50000;

	private int $nextConfluencePageNumber = 1;

	/**
	 * @return WorkspaceDB
	 */
	public function create(): WorkspaceDB {
		$workspaceDB = new WorkspaceDB( ':memory:' );

		$this->seedWikiConfig( $workspaceDB );
		$this->seedSpaces( $workspaceDB );
		$this->seedPages( $workspaceDB );
		$this->seedPageTemplates( $workspaceDB );

		return $workspaceDB;
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @return void
	 */
	private function seedWikiConfig( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addWikiConfig( 'SPC1', 'wiki-space-1', 'Space_1', '' );
		$workspaceDB->addWikiConfig( 'SPC2', 'wiki-space-2', 'Space_2', '' );
		$workspaceDB->addWikiConfig( 'SPC3', 'wiki-space-3', 'Space_3', '' );
		$workspaceDB->addWikiConfig( 'SPC4', 'wiki-space-4', 'Space_4', 'Space_4' );
		$workspaceDB->addWikiConfig( 'SPC5', 'wiki-space-4', 'Space_4', 'Space_5' );
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @return void
	 */
	private function seedSpaces( WorkspaceDB $workspaceDB ): void {
		$workspaceDB->addSpace( 1, 'SPC1', 'Space 1', 'Space_1', 'wiki-space_1', '', -1, -1 );
		$workspaceDB->addSpace( 2, 'SPC2', 'Space 2', 'Space_2', 'wiki-space_2', '', -1, -1 );
		$workspaceDB->addSpace( 3, 'SPC3', 'Space 3', 'Space_3', 'wiki-space_3', '', -1, -1 );
		$workspaceDB->addSpace( 4, 'SPC4', 'Space 4', 'Space_4', 'wiki-space_4', 'Space_4', -1, -1 );
		$workspaceDB->addSpace( 5, 'SPC5', 'Space 5', 'Space_4', 'wiki-space_4', 'Space_5', -1, -1 );
	}

	/**
	 * Seed 25 current pages per space (125 total), including main pages,
	 * parent/child hierarchy and history revisions.
	 *
	 * @param WorkspaceDB $workspaceDB
	 * @return void
	 */
	private function seedPages( WorkspaceDB $workspaceDB ): void {
		$spaceConfig = [
			1 => [ 'space_key' => 'SPC1', 'namespace' => 'Space_1', 'interwiki' => 'wiki-space_1', 'root_page' => '' ],
			2 => [ 'space_key' => 'SPC2', 'namespace' => 'Space_2', 'interwiki' => 'wiki-space_2', 'root_page' => '' ],
			3 => [ 'space_key' => 'SPC3', 'namespace' => 'Space_3', 'interwiki' => 'wiki-space_3', 'root_page' => '' ],
			4 => [ 'space_key' => 'SPC4', 'namespace' => 'Space_4', 'interwiki' => 'wiki-space_4', 'root_page' => 'Space_4' ],
			5 => [ 'space_key' => 'SPC5', 'namespace' => 'Space_4', 'interwiki' => 'wiki-space_4', 'root_page' => 'Space_5' ],
		];
		$crossSpaceTargets = $this->buildCrossSpaceTargets( $spaceConfig );

		foreach ( $spaceConfig as $spaceId => $config ) {
			$namespace = $config['namespace'];
			$interwikiPrefix = $config['interwiki'];
			$mainPageWikiName = $config['root_page'] !== '' ? $config['root_page'] : 'Main_page';
			$mainPageConfluenceName = $this->getNextConfluencePageTitle();
			$mainPageWikiTitle = "$namespace:$mainPageWikiName";
			$mainPageInterwikiTitle = "$interwikiPrefix:$mainPageWikiName";

			$mainPageId = $this->reservePageId();
			$mainHistoryIdOne = $this->reservePageId();
			$mainHistoryIdTwo = $this->reservePageId();

			$this->addCurrentPage(
				$workspaceDB,
				$mainPageId,
				$spaceId,
				$mainPageConfluenceName,
				$mainPageWikiTitle,
				$mainPageInterwikiTitle,
				-1,
				'20250101000000',
				'3',
				$crossSpaceTargets,
				[ $mainHistoryIdOne, $mainHistoryIdTwo ]
			);
			$workspaceDB->updateSpaceHomepageId( $spaceId, $mainPageId );

			for ( $parentIndex = 1; $parentIndex <= 6; $parentIndex++ ) {
				$parentPageId = $this->reservePageId();
				$parentHistoryId = $this->reservePageId();
				$parentConfluenceTitle = $this->getNextConfluencePageTitle();
				$parentWikiTitle = $mainPageWikiTitle . '/Page_' . $parentIndex;
				$parentInterwikiTitle = $mainPageInterwikiTitle . '/Page_' . $parentIndex;

				$childHistoryIds = [];
				$childPageData = [];
				for ( $childIndex = 1; $childIndex <= 3; $childIndex++ ) {
					$childPageId = $this->reservePageId();
					$childHistoryId = $this->reservePageId();
					$childHistoryIds[$childPageId] = $childHistoryId;
					$childPageData[] = [
						'child_page_id' => $childPageId,
						'child_index' => $childIndex,
					];
				}

				$this->addCurrentPage(
					$workspaceDB,
					$parentPageId,
					$spaceId,
					$parentConfluenceTitle,
					$parentWikiTitle,
					$parentInterwikiTitle,
					$mainPageId,
					'20250102000000',
					'3',
					$crossSpaceTargets,
					[ $parentHistoryId ]
				);

				$this->addHistoricalPageRevision(
					$workspaceDB,
					$parentHistoryId,
					$parentPageId,
					$parentConfluenceTitle,
					$mainPageId,
					'2',
					'20241210000000',
					$crossSpaceTargets
				);

				foreach ( $childPageData as $childData ) {
					$childPageId = $childData['child_page_id'];
					$childIndex = $childData['child_index'];
					$childHistoryId = $childHistoryIds[$childPageId];
					$childConfluenceTitle = $this->getNextConfluencePageTitle();
					$childWikiTitle = $parentWikiTitle . '/Page_' . $childIndex;
					$childInterwikiTitle = $parentInterwikiTitle . '/Page_' . $childIndex;

					$this->addCurrentPage(
						$workspaceDB,
						$childPageId,
						$spaceId,
						$childConfluenceTitle,
						$childWikiTitle,
						$childInterwikiTitle,
						$parentPageId,
						'20250103000000',
						'3',
						$crossSpaceTargets,
						[ $childHistoryId ]
					);

					$this->addHistoricalPageRevision(
						$workspaceDB,
						$childHistoryId,
						$childPageId,
						$childConfluenceTitle,
						$parentPageId,
						'1',
						'20241220000000',
						$crossSpaceTargets
					);
				}
			}

			$this->addHistoricalPageRevision(
				$workspaceDB,
				$mainHistoryIdOne,
				$mainPageId,
				$mainPageConfluenceName,
				-1,
				'1',
				'20241201000000',
				$crossSpaceTargets
			);

			$this->addHistoricalPageRevision(
				$workspaceDB,
				$mainHistoryIdTwo,
				$mainPageId,
				$mainPageConfluenceName,
				-1,
				'2',
				'20241215000000',
				$crossSpaceTargets
			);
		}
	}

	private function addCurrentPage(
		WorkspaceDB $workspaceDB,
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $interwikiTitle,
		int $parentPageId,
		string $revisionTimestamp,
		string $version,
		array $crossSpaceTargets,
		array $historicalIds
	): void {
		$bodyContentId = $this->addBodyContent(
			$workspaceDB,
			$pageId,
			$this->buildBodyContent( $confluenceTitle, $version, $crossSpaceTargets )
		);

		$workspaceDB->addPage(
			$pageId,
			$spaceId,
			$confluenceTitle,
			$wikiTitle,
			$interwikiTitle,
			'current',
			$revisionTimestamp,
			'',
			$version,
			-1,
			$parentPageId,
			[ $bodyContentId ],
			$historicalIds,
			[],
			[]
		);
	}

	private function addHistoricalPageRevision(
		WorkspaceDB $workspaceDB,
		int $pageId,
		int $originalVersionId,
		string $confluenceTitle,
		int $parentPageId,
		string $version,
		string $revisionTimestamp,
		array $crossSpaceTargets
	): void {
		$bodyContentId = $this->addBodyContent(
			$workspaceDB,
			$pageId,
			$this->buildBodyContent( $confluenceTitle, $version, $crossSpaceTargets )
		);

		$workspaceDB->addPage(
			$pageId,
			null,
			$confluenceTitle,
			'',
			'',
			'historical',
			$revisionTimestamp,
			'',
			$version,
			$originalVersionId,
			$parentPageId,
			[ $bodyContentId ],
			[],
			[],
			[]
		);
	}

	/**
	 * Seed page templates across different spaces to test interwiki template resolution.
	 *
	 * Templates in SPC1 are local when the current page is in SPC1.
	 * Templates in SPC2, SPC3, SPC4 require interwiki prefixes.
	 *
	 * @param WorkspaceDB $workspaceDB
	 * @return void
	 */
	private function seedPageTemplates( WorkspaceDB $workspaceDB ): void {
		// Template in SPC1 (spaceId=1) — local for pages in SPC1
		$workspaceDB->addPageTemplate( 2001, 'LocalTemplate', 1, 'Template:SPC1/LocalTemplate', 'wiki-space_1:Template:SPC1/LocalTemplate' );
		$workspaceDB->addPageTemplateContents( 2001, '<p>Template 2001: local template in SPC1. Same-wiki links should resolve to wiki_title.</p>' );

		// Template in SPC2 (spaceId=2) — remote; interwiki prefix: wiki-space_2
		$workspaceDB->addPageTemplate( 2002, 'RemoteTemplate', 2, 'Template:SPC2/RemoteTemplate', 'wiki-space_2:Template:SPC2/RemoteTemplate' );
		$workspaceDB->addPageTemplateContents( 2002, '<p>Template 2002: remote template in SPC2. Cross-wiki links should resolve to interwiki_title with prefix wiki-space_2.</p>' );

		// Template in SPC3 (spaceId=3) — remote; interwiki prefix: wiki-space_3
		$workspaceDB->addPageTemplate( 2003, 'AnotherRemoteTemplate', 3, 'Template:SPC3/AnotherRemoteTemplate', 'wiki-space_3:Template:SPC3/AnotherRemoteTemplate' );
		$workspaceDB->addPageTemplateContents( 2003, '<p>Template 2003: remote template in SPC3. Cross-wiki links should resolve to interwiki_title with prefix wiki-space_3.</p>' );

		// Template in SPC4 (spaceId=4) — remote; SPC4 and SPC5 share wiki-space_4
		$workspaceDB->addPageTemplate( 2004, 'SharedWikiTemplate', 4, 'Template:SPC4/SharedWikiTemplate', 'wiki-space_4:Template:SPC4/SharedWikiTemplate' );
		$workspaceDB->addPageTemplateContents( 2004, '<p>Template 2004: remote template in SPC4. SPC4 and SPC5 share wiki-space_4, so cross-wiki links resolve to interwiki_title with prefix wiki-space_4.</p>' );
	}

	private function reservePageId(): int {
		return $this->nextPageId++;
	}

	private function getNextConfluencePageTitle(): string {
		$title = 'Page ' . $this->nextConfluencePageNumber;
		$this->nextConfluencePageNumber++;

		return $title;
	}

	/**
	 * @param array<int,array{space_key:string,content_title:string,label:string}> $crossSpaceTargets
	 */
	private function buildBodyContent( string $confluenceTitle, string $version, array $crossSpaceTargets ): string {
		$links = '';
		foreach ( $crossSpaceTargets as $target ) {
			$links .= '<p><ac:link>'
				. '<ri:page ri:content-title="' . htmlspecialchars( $target['content_title'] )
				. '" ri:space-key="' . htmlspecialchars( $target['space_key'] ) . '" />'
				. '<ac:plain-text-link-body><![CDATA[' . $target['label']
				. ']]></ac:plain-text-link-body>'
				. '</ac:link></p>';
		}

		return '<h1>' . htmlspecialchars( $confluenceTitle ) . '</h1>'
			. '<p>This is version ' . htmlspecialchars( $version ) . ' of the page.</p>'
			. '<p>Cross-space links:</p>' . $links;
	}

	/**
	 * @param array<int,array{space_key:string,namespace:string,interwiki:string,root_page:string}> $spaceConfig
	 * @return array<int,array{space_key:string,content_title:string,label:string}>
	 */
	private function buildCrossSpaceTargets( array $spaceConfig ): array {
		$targets = [];
		$currentHomepageNumber = 1;
		foreach ( $spaceConfig as $spaceId => $config ) {
			$targets[] = [
				'space_key' => $config['space_key'],
				'content_title' => 'Page ' . $currentHomepageNumber,
				'label' => 'Space ' . $spaceId . ' homepage',
			];

			// Each space creates 25 current pages; the next homepage title follows that block.
			$currentHomepageNumber += 25;
		}

		return $targets;
	}

	private function addBodyContent( WorkspaceDB $workspaceDB, int $contentId, string $body ): int {
		$this->nextBodyContentId++;
		$workspaceDB->addBodyContent(
			$this->nextBodyContentId,
			$contentId,
			'Page',
			[]
		);
		$workspaceDB->addBodyContentBody( $this->nextBodyContentId, $body );

		return $this->nextBodyContentId;
	}
}
