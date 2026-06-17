<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * @see https://confluence.atlassian.com/doc/gallery-macro-139434.html for documentation
 * @see tests/phpunit/date/gallery-macro-input.xml for example input
 */
class GalleryMacro extends StructuredMacroProcessorBase {

	/** @var DBConversionDataLookup */
	private DBConversionDataLookup $dataLookup;

	/** @var int */
	private int $currentSpaceId;

	/** @var string */
	private string $rawPageTitle;

	/** @var MigrationConfig */
	private MigrationConfig $config;

	/** @var FilenameResolver */
	private FilenameResolver $filenameResolver;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param MigrationConfig $config
	 */
	public function __construct(
		DBConversionDataLookup $dataLookup,
		int $currentSpaceId,
		string $rawPageTitle,
		MigrationConfig $config
	) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'gallery';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->filenameResolver = new FilenameResolver( $this->dataLookup, $this->config );

		$macroName = $node->getAttribute( 'ac:name' );

		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-$macroName" );

		$params = $this->getMacroParams( $node );
		$bodyImages = $this->getBodyImages( $node );

		$hasBroken = false;
		if ( !empty( $bodyImages ) ) {
			$files = $this->resolveBodyImages( $bodyImages, $hasBroken );
		} else {
			$files = $this->getImageFiles( $params, $hasBroken );
		}

		if ( empty( $files ) ) {
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( $this->getBrokenMacroCategory() ),
				$node
			);

			return;
		}

		$galleryTag = '<gallery';
		if ( isset( $params['title'] ) && $params['title'] !== '' ) {
			$galleryTag .= ' caption="' . htmlspecialchars( $params['title'] ) . '"';
		}
		if ( isset( $params['columns'] ) && $params['columns'] !== '' ) {
			$galleryTag .= ' perrow="' . (int)$params['columns'] . '"';
		}
		$galleryTag .= ">\n";
		foreach ( $files as $file ) {
			$galleryTag .= $file . "\n";
		}
		$galleryTag .= '</gallery>';
		if ( $hasBroken ) {
			$galleryTag .= '[[Category:Broken_attachment_link]]';
		}

		$galleryTagNode = $node->ownerDocument->createTextNode( $galleryTag );
		$macroReplacement->appendChild( $galleryTagNode );

		if ( !empty( $params ) ) {
			$macroReplacement->setAttribute( 'data-params', json_encode( $params ) );
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param array $params
	 *
	 * @return string[]
	 */
	private function getImageFiles( array $params, bool &$hasBroken = false ): array {
		if ( isset( $params['include'] ) && $params['include'] !== '' ) {
			return $this->resolveIncludedFiles( $params['include'], $hasBroken );
		}

		$includeLabels = [];
		if ( isset( $params['includeLabel'] ) && $params['includeLabel'] !== '' ) {
			$includeLabels = array_map( 'trim', explode( ',', $params['includeLabel'] ) );
		}
		$excludeLabels = [];
		if ( isset( $params['excludeLabel'] ) && $params['excludeLabel'] !== '' ) {
			$excludeLabels = array_map( 'trim', explode( ',', $params['excludeLabel'] ) );
		}

		if ( isset( $params['page'] ) && $params['page'] !== '' ) {
			$allFiles = $this->resolvePageFiles( $params['page'], $includeLabels, $excludeLabels );
		} elseif ( !empty( $includeLabels ) || !empty( $excludeLabels ) ) {
			$allAttachments = $this->dataLookup->getAttachmentMetadataForPage(
				$this->currentSpaceId,
				$this->rawPageTitle
			);
			$allFiles = $this->filterAttachmentsByLabel( $allAttachments, $includeLabels, $excludeLabels );
		} else {
			$allFiles = array_merge(
				$this->dataLookup->getWikiFileTitlesForPage(
					$this->currentSpaceId,
					$this->rawPageTitle
				),
				$this->dataLookup->getWikiFileTitlesForBlogPost(
					$this->currentSpaceId,
					$this->rawPageTitle
				)
			);
		}

		$exclude = [];
		if ( isset( $params['exclude'] ) && $params['exclude'] !== '' ) {
			$exclude = array_map( 'trim', explode( ',', $params['exclude'] ) );
		}

		$files = [];
		foreach ( $allFiles as $file ) {
			if ( in_array( $file, $exclude ) ) {
				continue;
			}
			$files[] = $file;
		}

		return $files;
	}

	/**
	 * Resolves files from a comma-separated list of page references.
	 * Each entry may optionally be prefixed with a space key: "SPACEKEY:Page Title".
	 *
	 * @param string $pageParam Comma-separated page references
	 * @param string[] $includeLabels
	 * @param string[] $excludeLabels
	 * @return string[]
	 */
	private function resolvePageFiles(
		string $pageParam,
		array $includeLabels = [],
		array $excludeLabels = []
	): array {
		$pageRefs = array_map( 'trim', explode( ',', $pageParam ) );
		$files = [];
		foreach ( $pageRefs as $pageRef ) {
			$spaceId = $this->currentSpaceId;
			$pageTitle = $pageRef;

			if ( strpos( $pageRef, ':' ) !== false ) {
				[ $spaceKey, $pageTitle ] = explode( ':', $pageRef, 2 );
				$resolvedSpaceId = $this->resolveSpaceId( trim( $spaceKey ) );
				if ( $resolvedSpaceId === -1 ) {
					continue;
				}
				$spaceId = $resolvedSpaceId;
				$pageTitle = trim( $pageTitle );
			}

			if ( !empty( $includeLabels ) || !empty( $excludeLabels ) ) {
				$pageAttachments = array_merge(
					$this->dataLookup->getAttachmentMetadataForPage( $spaceId, $pageTitle ),
					$this->dataLookup->getAttachmentMetadataForBlogPost( $spaceId, $pageTitle )
				);
				$pageFiles = $this->filterAttachmentsByLabel( $pageAttachments, $includeLabels, $excludeLabels );
			} else {
				$pageFiles = array_merge(
					$this->dataLookup->getWikiFileTitlesForPage( $spaceId, $pageTitle ),
					$this->dataLookup->getWikiFileTitlesForBlogPost( $spaceId, $pageTitle )
				);
			}
			foreach ( $pageFiles as $file ) {
				$files[] = $file;
			}
		}
		return $files;
	}

	/**
	 * Filters an attachment metadata map by include/exclude label lists.
	 *
	 * includeLabels: AND logic – the attachment must carry ALL specified labels.
	 * excludeLabels: OR logic  – the attachment is excluded if it has ANY of the specified labels.
	 *
	 * @param array<string, array> $attachments Map of confluenceFileKey => metadata (with 'targetTitle')
	 * @param string[] $includeLabels
	 * @param string[] $excludeLabels
	 * @return string[]
	 */
	private function filterAttachmentsByLabel(
		array $attachments, array $includeLabels, array $excludeLabels
	): array {
		$files = [];
		foreach ( $attachments as $meta ) {
			$fileLabels = $meta['labels'] ?? [];
			if ( !empty( $includeLabels )
				&& count( array_intersect( $includeLabels, $fileLabels ) ) !== count( $includeLabels ) ) {
				continue;
			}
			if ( !empty( $excludeLabels ) && !empty( array_intersect( $excludeLabels, $fileLabels ) ) ) {
				continue;
			}
			$files[] = $meta['targetTitle'];
		}
		return $files;
	}

	/**
	 * @return string[]
	 */
	private function resolveIncludedFiles( string $include, bool &$hasBroken = false ): array {
		$filenames = array_map( 'trim', explode( ',', $include ) );
		$files = [];
		foreach ( $filenames as $filename ) {
			[ 'title' => $targetTitle, 'isBroken' => $isBroken ] =
				$this->filenameResolver->resolve( $this->currentSpaceId, $this->rawPageTitle, $filename );

			$files[] = $targetTitle;
			if ( $isBroken ) {
				$hasBroken = true;
			}
		}

		return $files;
	}

	/**
	 * @param DOMElement $macro
	 * @return DOMNode[]
	 */
	private function getBodyImages( DOMElement $macro ): array {
		$images = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:image' ) {
				$images[] = $childNode;
			}
		}
		return $images;
	}

	/**
	 * @param DOMNode[] $imageNodes
	 * @return string[]
	 */
	private function resolveBodyImages( array $imageNodes, bool &$hasBroken = false ): array {
		$files = [];
		foreach ( $imageNodes as $imageNode ) {
			$attachment = null;
			$isUrl = false;

			foreach ( $imageNode->childNodes as $child ) {
				if ( $child instanceof DOMElement && $child->nodeName === 'ri:attachment' ) {
					$attachment = $child;
				} elseif ( $child instanceof DOMElement && $child->nodeName === 'ri:url' ) {
					$isUrl = true;
				}
			}

			// External URLs are not supported by MediaWiki gallery
			if ( $isUrl || $attachment === null ) {
				continue;
			}

			$filename = $attachment->getAttribute( 'ri:filename' );
			if ( $filename === '' ) {
				continue;
			}

			$spaceId = $this->currentSpaceId;
			$pageTitle = $this->rawPageTitle;

			$page = null;
			foreach ( $attachment->childNodes as $child ) {
				if ( $child instanceof DOMElement && $child->nodeName === 'ri:page' ) {
					$page = $child;
					break;
				}
			}

			if ( $page !== null ) {
				$contentTitle = $page->getAttribute( 'ri:content-title' );
				if ( $contentTitle !== '' ) {
					$pageTitle = $contentTitle;
				}
				$spaceKey = $page->getAttribute( 'ri:space-key' );
				if ( $spaceKey !== '' ) {
					$resolvedSpaceId = $this->resolveSpaceId( $spaceKey );
					if ( $resolvedSpaceId !== -1 ) {
						$spaceId = $resolvedSpaceId;
					} else {
						continue;
					}
				}
			}

			[ 'title' => $targetTitle, 'isBroken' => $isBroken ] =
				$this->filenameResolver->resolve( $spaceId, $pageTitle, $filename );

			$files[] = $targetTitle;
			if ( $isBroken ) {
				$hasBroken = true;
			}
		}

		return $files;
	}

	/**
	 * @param string $spaceKey
	 * @return int|null
	 */
	private function resolveSpaceId( string $spaceKey ): ?int {
		return $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param DOMElement $macro
	 *
	 * @return array
	 */
	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement && $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === '' ) {
					continue;
				}
				$params[$paramName] = $childNode->nodeValue;
			}
		}

		return $params;
	}
}
