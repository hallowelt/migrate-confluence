<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use Exception;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ChildrenMacro extends StructuredMacroProcessorBase {

	/**
	 * @param int $spaceId
	 * @param string $wikiPageTitle
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		private int $spaceId,
		private string $wikiPageTitle,
		private DBConversionDataLookup $dataLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'children';
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$params = $this->processParams( $node );

		// if no page param was set resolve current page's wiki title as subpage root
		if ( !isset( $params['page'] ) ) {
			$params['page'] = $this->wikiPageTitle;
		}

		// page must not contain underscores
		$params['page'] = str_replace( '_', ' ', $params['page'] );

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';

		// Check for broken and handle
		if ( str_starts_with( $params['page'], 'Confluence---' ) ) {
			$wikiText .= $this->getBrokenMacroCategory();
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$textNode = $node->ownerDocument->createTextNode( $wikiText );

		$node->parentNode->replaceChild( $textNode, $node );
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return array
	 * @throws Exception
	 */
	private function processParams( DOMNode $node ): array {
		$params = [];

		foreach ( $node->childNodes as $paramNode ) {
			if ( $paramNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			if ( !$paramNode->hasAttributes() ) {
				continue;
			}

			$name = $paramNode->getAttribute( 'ac:name' );

			// Page param
			if ( $name === 'page' ) {
				$pageParamTitle = $this->processPageParam( $paramNode );

				// Fallback will be wiki page title if page param is invalid.
				if ( $pageParamTitle === null ) {
					continue;
				}

				$params['page'] = $pageParamTitle;

				continue;
			}

			// All other params
			$params[$name] = $paramNode->nodeValue;
		}

		return $params;
	}

	/**
	 * @param DOMNode $paramNode
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function processPageParam( DOMNode $paramNode ): ?string {
		if ( $paramNode->hasChildnodes() ) {
			foreach ( $paramNode->childNodes as $childNode ) {
				if ( $childNode->nodeName === 'ac:link' ) {
					$pageLinks = $childNode->getElementsByTagname( 'page' );
					if ( count( $pageLinks ) > 0 ) {
						$pageLink = $pageLinks->item( 0 );
						$resolved = $this->findChildWikiTitle( $pageLink );

						if ( $resolved !== null ) {
							return $resolved;
						}

						return $this->createBrokenTitle(
							$pageLink->getAttribute( 'ri:content-title' ),
							$pageLink->getAttribute( 'ri:space-key' )
						);
					}
				}
			}
		}

		return null;
	}

	/**
	 * @param DOMNode $pageLink
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function findChildWikiTitle( DOMNode $pageLink ): ?string {
		if ( !$pageLink->hasAttribute( 'ri:content-title' ) ) {
			return null;
		}

		$confluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
		if ( empty( $confluenceTitle ) ) {
			return null;
		}

		$spaceId = $this->spaceId;
		if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey(
				$pageLink->getAttribute( 'ri:space-key' )
			);
		}

		return $this->resolveWikiTitle( $spaceId, $confluenceTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 * @throws Exception
	 */
	private function resolveWikiTitle( int $spaceId, string $confluenceTitle ): ?string {
		$wikiTitle = $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );

		if ( $wikiTitle === null ) {
			return null;
		}

		return str_replace( ' ', '_', $wikiTitle );
	}

	/**
	 * @param string $confluenceTitle
	 * @param string|null $spaceKey
	 *
	 * @return string
	 */
	private function createBrokenTitle( string $confluenceTitle, ?string $spaceKey = null ): string {
		if ( empty( $spaceKey ) ) {
			return "Confluence---------$confluenceTitle";
		}

		return "Confluence---$spaceKey---$confluenceTitle";
	}
}
