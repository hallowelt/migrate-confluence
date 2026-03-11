<?php

namespace HalloWelt\MigrateConfluence\Composer;

use HalloWelt\MediaWiki\Lib\MediaWikiXML\Builder;
use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MediaWiki\Lib\Migration\Workspace;
use HalloWelt\MigrateConfluence\Utility\DrawIOFileHandler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\Output;

/**
 * Handles multi-wiki XML output for the Compose step.
 *
 * Each wiki defined in the `wikis` config block gets its own output directory:
 *   result/{WikiName}/output.xml   — MediaWiki import XML
 *   result/{WikiName}/images/      — attachments belonging to pages in that wiki
 *
 * Page titles and intra-wiki wikitext links are translated from the global
 * namespace prefix (e.g. "A:") to the target namespace prefix configured per
 * wiki (e.g. "" for NS_MAIN or "MyNS:").  Cross-wiki links (pointing to a
 * different space) are left untouched so they remain valid interwiki references.
 */
class MultiWikiComposer {

	/** @var array wikiName → [ 'spaces' => [ spaceKey => targetNsPrefix ] ] */
	private $wikisConfig;

	/** @var DataBuckets */
	private $buckets;

	/** @var Workspace */
	private $workspace;

	/** @var array */
	private $advancedConfig;

	/** @var Output */
	private $output;

	/** @var string Absolute path to the destination directory */
	private $dest;

	/** @var DataBuckets */
	private $customBuckets;

	/**
	 * Maps global namespace prefix (e.g. "A:") to
	 * [ 'wiki' => wikiName, 'targetPrefix' => targetNsPrefix ].
	 *
	 * @var array
	 */
	private $namespaceToWikiMap = [];

	/**
	 * One Builder per wiki name.
	 *
	 * @var Builder[]
	 */
	private $wikiBuilders = [];

	/**
	 * @param array $wikisConfig
	 * @param DataBuckets $buckets
	 * @param Workspace $workspace
	 * @param array $advancedConfig
	 * @param Output $output
	 * @param string $dest
	 * @param DataBuckets $customBuckets
	 */
	public function __construct(
		array $wikisConfig,
		DataBuckets $buckets,
		Workspace $workspace,
		array $advancedConfig,
		Output $output,
		string $dest,
		DataBuckets $customBuckets
	) {
		$this->wikisConfig = $wikisConfig;
		$this->buckets = $buckets;
		$this->workspace = $workspace;
		$this->advancedConfig = $advancedConfig;
		$this->output = $output;
		$this->dest = $dest;
		$this->customBuckets = $customBuckets;
	}

	/**
	 * Run the full multi-wiki compose pass.
	 * Pages are routed to per-wiki builders, namespace prefixes are translated,
	 * intra-wiki links are rewritten, and separate output files are written.
	 *
	 * @return void
	 */
	public function compose(): void {
		$this->buildNamespaceToWikiMap();

		foreach ( array_keys( $this->wikisConfig ) as $wikiName ) {
			$this->wikiBuilders[$wikiName] = new Builder();
		}

		foreach ( $this->wikiBuilders as $wikiName => $wikiBuilder ) {
			$this->appendDefaultPagesToBuilder( $wikiBuilder );
			$this->addDefaultFiles( $wikiName );
		}

		$spaceIdHomepagesMap = $this->buckets->getBucketData( 'global-space-id-homepages' );
		$homepagespaceIdMap = array_flip( $spaceIdHomepagesMap );
		$spaceIdDescriptionIdMap = $this->buckets->getBucketData(
			'global-space-id-to-description-id-map'
		);
		$spaceBodyIdDescriptionIdBodyIDMap = $this->buckets->getBucketData(
			'global-body-content-id-to-space-description-id-map'
		);
		$titleRevisions = $this->buckets->getBucketData( 'global-title-revisions' );

		$bodyContentIdMainpageId = $this->buildMainpageContentMap( $spaceIdHomepagesMap );

		foreach ( $titleRevisions as $pageTitle => $pageRevisions ) {
			if ( $this->skipTitle( $pageTitle ) ) {
				continue;
			}

			$wikiEntry = $this->getWikiEntryForTitle( $pageTitle );
			if ( $wikiEntry === null ) {
				$this->output->writeln(
					"Page '$pageTitle' does not belong to any configured wiki — skipping."
				);
				continue;
			}

			[ 'wiki' => $wikiName, 'sourcePrefix' => $sourcePrefix, 'targetPrefix' => $targetPrefix ]
				= $wikiEntry;

			$sortedRevisions = $this->sortRevisions( $pageRevisions );
			foreach ( $sortedRevisions as $timestamp => $bodyContentIds ) {
				$bodyContentIdsArr = explode( '/', $bodyContentIds );
				$pageContent = '';
				foreach ( $bodyContentIdsArr as $bodyContentId ) {
					if ( $bodyContentId === '' ) {
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

				$targetTitle = $this->translatePageTitle( $pageTitle, $sourcePrefix, $targetPrefix );
				$translatedContent = $this->rewriteIntraWikiLinks(
					$pageContent, $sourcePrefix, $targetPrefix
				);

				$this->wikiBuilders[$wikiName]->addRevision(
					$targetTitle, $translatedContent, $timestamp
				);

				$this->addTitleAttachments( $pageTitle, $wikiName );
			}
		}

		$this->writeOutputFiles();
	}

	/**
	 * Build a map from global namespace prefix to wiki routing info.
	 * Uses global-space-key-to-prefix-map bucket data to resolve the global prefix
	 * for each space key listed in the wikis config.
	 *
	 * @return void
	 */
	private function buildNamespaceToWikiMap(): void {
		$spaceKeyToPrefixMap = $this->buckets->getBucketData( 'global-space-key-to-prefix-map' );

		foreach ( $this->wikisConfig as $wikiName => $wikiDef ) {
			$spaces = $wikiDef['spaces'] ?? [];
			foreach ( $spaces as $spaceKey => $targetPrefix ) {
				if ( isset( $spaceKeyToPrefixMap[$spaceKey] ) ) {
					$sourcePrefix = $spaceKeyToPrefixMap[$spaceKey];
				} else {
					$sourcePrefix = ( $spaceKey === 'GENERAL' ) ? '' : ( $spaceKey . ':' );
				}

				$this->namespaceToWikiMap[$sourcePrefix] = [
					'wiki' => $wikiName,
					'targetPrefix' => (string)$targetPrefix,
				];
			}
		}
	}

	/**
	 * Determine which wiki a page belongs to based on its title prefix.
	 * Returns an array with keys 'wiki', 'sourcePrefix', 'targetPrefix', or null if unmapped.
	 *
	 * @param string $pageTitle
	 * @return array|null
	 */
	private function getWikiEntryForTitle( string $pageTitle ): ?array {
		$colonPos = strpos( $pageTitle, ':' );
		$sourcePrefix = ( $colonPos !== false ) ? substr( $pageTitle, 0, $colonPos + 1 ) : '';

		if ( !isset( $this->namespaceToWikiMap[$sourcePrefix] ) ) {
			return null;
		}

		$entry = $this->namespaceToWikiMap[$sourcePrefix];
		return [
			'wiki' => $entry['wiki'],
			'sourcePrefix' => $sourcePrefix,
			'targetPrefix' => $entry['targetPrefix'],
		];
	}

	/**
	 * Translate a page title by replacing the source namespace prefix with the target prefix.
	 * Example: translatePageTitle('A:SomePage', 'A:', '') → 'SomePage'
	 * Example: translatePageTitle('A:SomePage', 'A:', 'MyNS:') → 'MyNS:SomePage'
	 *
	 * @param string $title
	 * @param string $sourcePrefix
	 * @param string $targetPrefix
	 * @return string
	 */
	public function translatePageTitle( string $title, string $sourcePrefix, string $targetPrefix ): string {
		if ( $sourcePrefix === '' ) {
			return $targetPrefix . $title;
		}
		return $targetPrefix . substr( $title, strlen( $sourcePrefix ) );
	}

	/**
	 * Rewrite intra-wiki wikitext links by replacing the source namespace prefix
	 * with the target namespace prefix in all [[...]] link targets.
	 *
	 * Example: rewriteIntraWikiLinks('[[A:Page|label]]', 'A:', '') → '[[Page|label]]'
	 * Example: rewriteIntraWikiLinks('[[A:Page]]', 'A:', 'MyNS:') → '[[MyNS:Page]]'
	 *
	 * Links to other spaces (different prefix) are left untouched, so cross-wiki
	 * references remain intact (e.g. [[B:PageB]] in a WikiA page stays as-is).
	 *
	 * @param string $content
	 * @param string $sourcePrefix
	 * @param string $targetPrefix
	 * @return string
	 */
	public function rewriteIntraWikiLinks(
		string $content, string $sourcePrefix, string $targetPrefix
	): string {
		if ( $sourcePrefix === '' ) {
			return $content;
		}
		$escapedPrefix = preg_quote( $sourcePrefix, '/' );
		return preg_replace( '/\[\[' . $escapedPrefix . '/', '[[' . $targetPrefix, $content );
	}

	/**
	 * Append default (boilerplate) pages to a given builder.
	 *
	 * @param Builder $builder
	 * @return void
	 */
	private function appendDefaultPagesToBuilder( Builder $builder ): void {
		$basepath = __DIR__ . '/_defaultpages/';
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

			$builder->addRevision( $wikiPageName, $wikiText );
		}
	}

	/**
	 * Copy default upload files into the wiki-specific images folder.
	 *
	 * @param string $wikiName
	 * @return void
	 */
	private function addDefaultFiles( string $wikiName ): void {
		$basepath = __DIR__ . '/_defaultfiles/';
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $basepath ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $fileObj ) {
			if ( $fileObj->isDir() ) {
				continue;
			}
			$file = $fileObj->getPathname();
			$fileName = basename( $file );
			$data = file_get_contents( $file );

			$this->workspace->saveUploadFile( $fileName, $data, $this->imagesPath( $wikiName ) );
		}
	}

	/**
	 * Add attachments belonging to $pageTitle into the wiki-specific images folder.
	 *
	 * @param string $pageTitle  The original (global-prefix) title used as bucket key.
	 * @param string $wikiName
	 * @return void
	 */
	private function addTitleAttachments( string $pageTitle, string $wikiName ): void {
		$pageAttachmentsMap = $this->buckets->getBucketData( 'global-title-attachments' );
		$filesMap = $this->buckets->getBucketData( 'global-files' );

		if ( empty( $pageAttachmentsMap[$pageTitle] ) ) {
			return;
		}

		$this->output->writeln( "\nPage has attachments. Adding them...\n" );

		$drawIoFileHandler = new DrawIOFileHandler();
		foreach ( $pageAttachmentsMap[$pageTitle] as $attachment ) {
			$this->output->writeln( "Attachment: $attachment" );

			if ( $drawIoFileHandler->isDrawIODataFile( $attachment ) ) {
				continue;
			}

			if ( isset( $filesMap[$attachment] ) ) {
				$filePath = $filesMap[$attachment][0];
				$attachmentContent = file_get_contents( $filePath );

				$this->workspace->saveUploadFile(
					$attachment, $attachmentContent, $this->imagesPath( $wikiName )
				);
				$this->customBuckets->addData( 'title-uploads', $pageTitle, $attachment );
			} else {
				$this->output->writeln( "Attachment file was not found!" );
				$this->customBuckets->addData( 'title-uploads-fail', $pageTitle, $attachment );
			}
		}
	}

	/**
	 * Write one output XML file per wiki under result/{WikiName}/output.xml.
	 *
	 * @return void
	 */
	private function writeOutputFiles(): void {
		foreach ( $this->wikiBuilders as $wikiName => $wikiBuilder ) {
			$safeName = preg_replace( '/[^A-Za-z0-9_\-]/', '_', $wikiName );
			$dir = $this->dest . "/result/{$safeName}";
			if ( !is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
			$wikiBuilder->buildAndSave( "{$dir}/output.xml" );
		}
	}

	/**
	 * Return the workspace-relative images path for a given wiki.
	 *
	 * @param string $wikiName
	 * @return string e.g. "result/WikiA/images"
	 */
	private function imagesPath( string $wikiName ): string {
		$safeName = preg_replace( '/[^A-Za-z0-9_\-]/', '_', $wikiName );
		return "result/{$safeName}/images";
	}

	/**
	 * Decide whether a page title should be skipped based on config.
	 *
	 * @param string $pageTitle
	 * @return bool
	 */
	private function skipTitle( string $pageTitle ): bool {
		$namespace = $this->getNamespace( $pageTitle );
		if (
			isset( $this->advancedConfig['composer-skip-namespace'] )
			&& in_array( $namespace, $this->advancedConfig['composer-skip-namespace'] )
		) {
			$this->output->writeln( "Namespace {$namespace} skipped by configuration" );
			return true;
		}

		if (
			isset( $this->advancedConfig['composer-skip-titles'] )
			&& in_array( $pageTitle, $this->advancedConfig['composer-skip-titles'] )
		) {
			$this->output->writeln( "Page {$pageTitle} skipped by configuration" );
			return true;
		}
		return false;
	}

	/**
	 * @param string $title
	 * @return string
	 */
	private function getNamespace( string $title ): string {
		$colonPos = strpos( $title, ':' );
		if ( !$colonPos ) {
			return 'NS_MAIN';
		}
		return substr( $title, 0, $colonPos );
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
			$timestamp = $versionTimestamp[1];

			$sortedRevisions[$bodyContentIds] = $timestamp;
		}

		natsort( $sortedRevisions );
		$sortedRevisions = array_flip( $sortedRevisions );

		if ( !$this->includeHistory() ) {
			$bodyContentIds = end( $sortedRevisions );
			$timestamp = array_search( $bodyContentIds, $sortedRevisions );
			$sortedRevisions = [ $timestamp => $bodyContentIds ];
		}

		return $sortedRevisions;
	}

	/**
	 * @return bool
	 */
	private function includeHistory(): bool {
		if ( isset( $this->advancedConfig['include-history'] )
			&& $this->advancedConfig['include-history'] !== true
		) {
			return true;
		}
		return false;
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
	 * Add space description to homepage content.
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

		if ( !isset( $bodyContentIdMainpageId[$bodyContentId] ) ) {
			return $pageContent;
		}

		$mainpageID = $bodyContentIdMainpageId[$bodyContentId];
		if ( !isset( $homepagespaceIdMap[$mainpageID] ) ) {
			return $pageContent;
		}

		$spaceId = $homepagespaceIdMap[$mainpageID];
		if ( !isset( $spaceIdDescriptionIdMap[$spaceId] ) ) {
			return $pageContent;
		}

		$descId = $spaceIdDescriptionIdMap[$spaceId];
		if ( !isset( $spaceDescriptionIdBodyIdMap[$descId] ) ) {
			return $pageContent;
		}

		$descBodyId = $spaceDescriptionIdBodyIdMap[$descId];
		$description = $this->workspace->getConvertedContent( $descBodyId );
		if ( $description !== '' ) {
			$pageContent .= "[[Space description::$description]]\n";
		}

		return $pageContent;
	}
}
