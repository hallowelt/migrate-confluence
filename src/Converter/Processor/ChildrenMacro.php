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
				if ( $paramNode->hasChildnodes() ) {
					foreach ( $paramNode->childNodes as $childNode ) {
						// page param is a ac:link node
						if ( $childNode->nodeName === 'ac:link' ) {
							$pageLinks = $childNode->getElementsByTagname( 'page' );
							if ( count( $pageLinks ) > 0 ) {
								$pageLink = $pageLinks->item( 0 );
								$spaceId = $this->spaceId;

								// Get space key if set. Otherwise use current space key
								$spaceKey = $this->dataLookup->getSpaceKeyFromSpaceId( $this->spaceId );
								if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
									$spaceKey = $pageLink->getAttribute( 'ri:space-key' );
									$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
									// TODO: Log if spaceId is null, but we should be able to resolve the filename
									// without spaceId as well, so we can continue processing
								}

								// Get confluence page title if set
								if ( $pageLink->hasAttribute( 'ri:content-title' ) ) {
									$pageConfluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
									if ( $pageConfluenceTitle === '' ) {
										// If no page title can be found mark macro as broken
										$params[$name] = str_replace(
											' ', '_', "Confluence---$spaceKey---(no confluence title found)"
										);
										$isBroken = true;
										break;
									}

									$wikiTitle = $this->dataLookup->getWikiPageTitleFromSpaceId(
										$spaceId,
										$pageConfluenceTitle
									);

									if ( $wikiTitle === null ) {
										// If wiki page title is empty mark macro as broken
										$params[$name] = str_replace(
											' ', '_', "Confluence---$spaceKey---$pageConfluenceTitle"
										);
										$isBroken = true;
										break;
									}

									$params[$name] = $wikiTitle;
								} else {
									// If no page title was found set empty page title and mark macro as broken
									$isBroken = true;
									$params[$name] = '';
								}
							}
						}
					}
				} else {
					// Fallback if param 'page' doesn't have a ac:link child element
					$params[$name] = $paramNode->nodeValue;
				}
			} else {
				// All other params
				$params[$name] = $paramNode->nodeValue;
			}
		}

		if ( !isset( $params['page'] ) ) {
			// if no page param was set pass current page title to subpage template
			$params['page'] = $this->wikiPageTitle;
		}

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			// page param must not contain underscores
			if ( $key === 'page' && !$isBroken ) {
				$value = str_replace( '_', ' ', $value );
			}

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
}
