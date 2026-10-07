<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the Confluence "attachments" macro into an EnhancedUpload <attachments> tag
 * at the position of the macro.
 *
 * The <attachments> tag only lists files that are linked inside its body, so the
 * file list has to be resolved at conversion time.
 *
 * Supported parameters:
 * - page:      list attachments of another page (ri:page or "SPACEKEY:Title")
 * - patterns:  comma-separated regular expressions matched against the original filename
 * - labels:    comma-separated labels; an attachment must carry all of them
 * - sortBy:    "name" is applied; other values keep the default order
 * - sortOrder: "ascending" / "descending" (only together with sortBy=name)
 *
 * Not supported (no equivalent in EnhancedUpload): old, upload, preview.
 *
 * @see https://confluence.atlassian.com/doc/attachments-macro-139388.html
 */
class AttachmentsMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'attachments';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'EnhancedUpload' ];

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		private readonly DBConversionDataLookup $dataLookup,
		private readonly int $currentSpaceId,
		private readonly string $rawPageTitle,
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );

		$broken = false;
		$attachments = $this->getAttachments( $params, $broken );
		$attachments = $this->filterByPatterns( $attachments, $params['patterns'] ?? '' );
		$attachments = $this->filterByLabels( $attachments, $params['labels'] ?? '' );
		$files = $this->sortFiles( $attachments, $params );

		$wikiText = "\n<attachments>\n";
		foreach ( $files as $file ) {
			$wikiText .= "* [[Media:$file]]\n";
		}
		$wikiText .= "</attachments>\n";
		if ( $broken ) {
			$wikiText .= $this->getBrokenMacroCategory() . "\n";
		}

		// Text node with a placeholder: pandoc drops unknown elements and escapes "<" in text
		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( $wikiText ),
				__METHOD__
			),
			$node
		);
	}

	/**
	 * @param array $params
	 * @param bool &$broken set to true if a referenced page could not be resolved
	 * @return array<string, array> original filename => metadata (with 'targetTitle')
	 */
	private function getAttachments( array $params, bool &$broken ): array {
		$spaceId = $this->currentSpaceId;
		$pageTitle = $this->rawPageTitle;

		if ( isset( $params['page'] ) ) {
			[ 'spaceKey' => $spaceKey, 'title' => $title ] = $params['page'];
			if ( $title !== '' ) {
				$pageTitle = $title;
			}
			if ( $spaceKey !== '' ) {
				$resolvedSpaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
				if ( $resolvedSpaceId === null || $resolvedSpaceId === -1 ) {
					$broken = true;
					return [];
				}
				$spaceId = $resolvedSpaceId;
			}
		}

		$attachments = array_merge(
			$this->dataLookup->getAttachmentMetadataForPage( $spaceId, $pageTitle ),
			$this->dataLookup->getAttachmentMetadataForBlogPost( $spaceId, $pageTitle )
		);

		if ( isset( $params['page'] ) && empty( $attachments ) ) {
			// Referenced page unknown or without attachments; cannot tell apart here
			$broken = true;
		}

		return $attachments;
	}

	/**
	 * Confluence matches each pattern against the whole filename (Java String::matches)
	 */
	private function filterByPatterns( array $attachments, string $patternParam ): array {
		$patterns = $this->splitList( $patternParam );
		if ( empty( $patterns ) ) {
			return $attachments;
		}

		$regexes = [];
		foreach ( $patterns as $pattern ) {
			$regex = '/^(?:' . str_replace( '/', '\/', $pattern ) . ')$/u';
			// Skip invalid expressions instead of failing the whole page
			if ( preg_match( $regex, '' ) !== false ) {
				$regexes[] = $regex;
			}
		}
		if ( empty( $regexes ) ) {
			return $attachments;
		}

		$filtered = [];
		foreach ( $attachments as $originalFilename => $meta ) {
			foreach ( $regexes as $regex ) {
				if ( preg_match( $regex, (string)$originalFilename ) === 1 ) {
					$filtered[$originalFilename] = $meta;
					break;
				}
			}
		}
		return $filtered;
	}

	private function filterByLabels( array $attachments, string $labelParam ): array {
		$labels = $this->splitList( $labelParam );
		if ( empty( $labels ) ) {
			return $attachments;
		}

		$filtered = [];
		foreach ( $attachments as $originalFilename => $meta ) {
			// The extractor stores attachment labels as 'categories'
			$fileLabels = $meta['labels'] ?? $meta['categories'] ?? [];
			if ( count( array_intersect( $labels, $fileLabels ) ) === count( $labels ) ) {
				$filtered[$originalFilename] = $meta;
			}
		}
		return $filtered;
	}

	private function sortFiles( array $attachments, array $params ): array {
		$files = [];
		foreach ( $attachments as $meta ) {
			if ( isset( $meta['targetTitle'] ) && $meta['targetTitle'] !== '' ) {
				$files[] = $meta['targetTitle'];
			}
		}
		$files = array_values( array_unique( $files ) );

		// Without date/size in the lookup, only name sorting is possible; default to name
		natcasesort( $files );
		$files = array_values( $files );

		$sortBy = strtolower( $params['sortBy'] ?? '' );
		$sortOrder = strtolower( $params['sortOrder'] ?? '' );
		if ( $sortBy === 'name' && $sortOrder === 'descending' ) {
			$files = array_reverse( $files );
		}

		return $files;
	}

	/**
	 * @return string[]
	 */
	private function splitList( string $value ): array {
		return array_values( array_filter(
			array_map( 'trim', explode( ',', $value ) ),
			static fn ( $item ) => $item !== ''
		) );
	}

	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( !( $childNode instanceof DOMElement ) || $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}
			$name = $childNode->getAttribute( 'ac:name' );
			if ( $name === '' ) {
				continue;
			}
			if ( $name === 'page' ) {
				$params['page'] = $this->getPageParam( $childNode );
				continue;
			}
			$params[$name] = trim( $childNode->nodeValue ?? '' );
		}
		return $params;
	}

	/**
	 * The page parameter is either <ac:link><ri:page .../></ac:link> or plain "SPACEKEY:Title"
	 *
	 * @return array{spaceKey: string, title: string}
	 */
	private function getPageParam( DOMElement $param ): array {
		$pageNodes = $param->getElementsByTagName( 'page' );
		foreach ( $pageNodes as $pageNode ) {
			if ( $pageNode instanceof DOMElement && $pageNode->nodeName === 'ri:page' ) {
				return [
					'spaceKey' => $pageNode->getAttribute( 'ri:space-key' ),
					'title' => $pageNode->getAttribute( 'ri:content-title' ),
				];
			}
		}

		$value = trim( $param->nodeValue ?? '' );
		if ( str_contains( $value, ':' ) ) {
			[ $spaceKey, $title ] = explode( ':', $value, 2 );
			return [ 'spaceKey' => trim( $spaceKey ), 'title' => trim( $title ) ];
		}
		return [ 'spaceKey' => '', 'title' => $value ];
	}
}
