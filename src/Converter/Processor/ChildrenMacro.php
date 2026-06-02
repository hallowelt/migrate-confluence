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
	 * @param string $currentWikiTitle
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		private int $spaceId,
		private string $currentWikiTitle,
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

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';
		if ( $this->isBroken ) {
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
	 */
	private function processPageParam( DOMNode $paramNode ): string {
		// Fallback if param 'page' doesn't have a ac:link child element
		// TODO: which of them is correct fallback?
		$pageName = $paramNode->nodeValue;
		$pageName = $this->currentWikiTitle;

		if ( $paramNode->hasChildnodes() ) {
			foreach ( $paramNode->childNodes as $childNode ) {
				if ( $childNode->nodeName === 'ac:link' ) {
					$pageLinks = $childNode->getElementsByTagname( 'page' );
					if ( count( $pageLinks ) > 0 ) {
						$pageLink = $pageLinks->item( 0 );

						$childWikiTitle = $this->findChildWikiTitle( $pageLink );

						if ( !$childWikiTitle ) {
							$this->isBroken = true;
							continue;
						}

						$pageName = $childWikiTitle;
					}
				}
			}
		}

		// page param must not contain underscores
		return str_replace( '_', ' ', $pageName );
	}

	/**
	 * @param DOMNode $pageLink
	 *
	 * @return string|null
	 */
	private function findChildWikiTitle( DOMNode $pageLink ): ?string {
		// If no confluence title was found set empty page title and mark macro as broken
		if ( !$pageLink->hasAttribute( 'ri:content-title' ) ) {
			return null;
		}

		$pageConfluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
		if ( empty( $pageConfluenceTitle ) ) {
			return null;
		}

		$spaceId = $this->spaceId;

		// Get space key if set. Otherwise use current space key
		$spaceKey = '';
		if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey(
				$pageLink->getAttribute( 'ri:space-key' )
			);
		}

		try {
			return $this->dataLookup->getTargetWikiTitleFromSpaceId(
				$spaceId,
				$pageConfluenceTitle
			);
		} catch ( Exception $e ) {
			// If no page title can be found mark macro as broken
			$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );

			return "Confluence---$spaceKey---$pageConfluenceTitle";
		}
	}
}
