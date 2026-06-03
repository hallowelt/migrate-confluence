<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use Exception;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ChildrenMacro extends StructuredMacroProcessorBase {

	/**
	 * @param int $spaceId
	 * @param string $confluencePageTitle
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		private int $spaceId,
		private string $confluencePageTitle,
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
		$isBroken = false;

		$paramNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramNodes[] = $childNode;
			}
		}

		$params = [];
		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}

			$name = $paramNode->getAttribute( 'ac:name' );

			if ( $name === 'page' ) {
				$params['page'] = $this->processPageParam( $paramNode );
				if ( str_starts_with( $params['page'], 'Confluence---' ) ) {
					$isBroken = true;
				}

				continue;
			}

			// All other params
			$params[$name] = $paramNode->nodeValue;
		}

		if ( !isset( $params['page'] ) ) {
			// if no page param was set resolve current page's wiki title as subpage root
			$resolved = $this->resolveWikiTitle( $this->spaceId, $this->confluencePageTitle );
			$params['page'] = $resolved ?? "Confluence---{$this->confluencePageTitle}";
			if ( $resolved === null ) {
				$isBroken = true;
			}
		}

		// page must not contain underscores
		$params['page'] = str_replace( '_', ' ', $params['page'] );

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';
		if ( $isBroken ) {
			$wikiText .= $this->getBrokenMacroCategory();
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$textNode = $node->ownerDocument->createTextNode( $wikiText );

		$node->parentNode->replaceChild( $textNode, $node );
	}

	/**
	 * @param DOMNode $paramNode
	 *
	 * @return string
	 * @throws Exception
	 */
	private function processPageParam( DOMNode $paramNode ): string {
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
						$confluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
						$spaceKey = $pageLink->getAttribute( 'ri:space-key' );
						return $spaceKey !== ''
							? "Confluence---{$spaceKey}---{$confluenceTitle}"
							: "Confluence---{$confluenceTitle}";
					}
				}
			}
		}

		// Fallback if param 'page' doesn't have a ac:link child element
		return $this->resolveWikiTitle( $this->spaceId, $this->confluencePageTitle )
			?? "Confluence---{$this->confluencePageTitle}";
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
		$wikiTitle = $this->dataLookup->getTargetWikiTitleFromSpaceId( $spaceId, $confluenceTitle );

		if ( $wikiTitle === null ) {
			return null;
		}

		return str_replace( ' ', '_', $wikiTitle );
	}
}
