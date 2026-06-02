<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use Exception;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ChildrenMacro extends StructuredMacroProcessorBase {

	/** @var bool */
	private bool $isBroken;

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
		$this->isBroken = false;
	}

	/**
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'children';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
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

				continue;
			}

			// All other params
			$params[$name] = $paramNode->nodeValue;
		}

		if ( !isset( $params['page'] ) ) {
			// if no page param was set resolve current page's wiki title as subpage root
			$params['page'] = $this->resolveWikiTitle( $this->spaceId, '', $this->confluencePageTitle );
		}

		// page must not contain underscores
		$params['page'] = str_replace( '_', ' ', $params['page'] );

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';
		if ( $this->isBroken ) {
			$wikiText .= $this->getBrokenMacroCategory();
			$this->isBroken = false;
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$textNode = $node->ownerDocument->createTextNode( $wikiText );

		$node->parentNode->replaceChild( $textNode, $node );
	}

	/**
	 * @param DOMNode $paramNode
	 *
	 * @return string
	 */
	private function processPageParam( DOMNode $paramNode ): string {
		$pageName = null;

		if ( $paramNode->hasChildnodes() ) {
			foreach ( $paramNode->childNodes as $childNode ) {
				if ( $childNode->nodeName === 'ac:link' ) {
					$pageLinks = $childNode->getElementsByTagname( 'page' );
					if ( count( $pageLinks ) > 0 ) {
						$pageLink = $pageLinks->item( 0 );

						$childWikiTitle = $this->findChildWikiTitle( $pageLink );

						if ( !$childWikiTitle ) {
							continue;
						}

						$pageName = $childWikiTitle;
					}
				}
			}
		}

		// Fallback if param 'page' doesn't have a ac:link child element
		if ( !$pageName ) {
			$pageName = $this->resolveWikiTitle( $this->spaceId, '', $this->confluencePageTitle );
		}

		return $pageName;
	}

	/**
	 * @param DOMNode $pageLink
	 *
	 * @return string|null
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
		$spaceKey = '';
		if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
			$spaceKey = $pageLink->getAttribute( 'ri:space-key' );
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		return $this->resolveWikiTitle( $spaceId, $spaceKey, $confluenceTitle );
	}

	/**
	 * @param int $spaceId
	 * @param string $spaceKey Empty string when using the current page's space
	 * @param string $confluenceTitle
	 *
	 * @return string
	 */
	private function resolveWikiTitle( int $spaceId, string $spaceKey, string $confluenceTitle ): string {
		try {
			$wikiTitle = $this->dataLookup->getTargetWikiTitleFromSpaceId( $spaceId, $confluenceTitle );
		} catch ( Exception $e ) {
			$this->isBroken = true;
			$wikiTitle = "Confluence---$spaceKey---$confluenceTitle";
		}

		return str_replace( ' ', '_', $wikiTitle );
	}
}
