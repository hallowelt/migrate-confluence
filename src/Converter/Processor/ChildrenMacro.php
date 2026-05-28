<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ChildrenMacro extends StructuredMacroProcessorBase {

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
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'children';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$broken = false;
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
						// page param is a a ac:link node
						if ( $childNode->nodeName === 'ac:link' ) {
							$pageLinks = $childNode->getElementsByTagname( 'page' );
							if ( count( $pageLinks ) > 0 ) {
								$pageLink = $pageLinks->item( 0 );
								$spaceId = $this->spaceId;

								// Get space key if set. Otherwise use current space key
								$spaceKey = '';
								if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
									$spaceKey = $pageLink->getAttribute( 'ri:space-key' );
									$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
								}

								// Get confluence page title if set
								if ( $pageLink->hasAttribute( 'ri:content-title' ) ) {
									$pageConfluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
									if ( $pageConfluenceTitle === '' ) {
										// If no page title can be found mark macro as broken
										$broken = true;

										$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
										$params[$name] = "Confluence---$spaceKey---$pageConfluenceTitle";
										break;
									}

									$params[$name] = $this->dataLookup->getTargetWikiTitleFromSpaceId(
										$spaceId,
										$pageConfluenceTitle
									);

									if ( $params[$name] === '' ) {
										// If wiki page title is empty mark macro as broken
										$params[$name] = "Confluence---$spaceKey---$pageConfluenceTitle";
										$broken = true;
										break;
									}
								} else {
									// If no page title was found set empty page title and mark macro as broken
									$broken = true;
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
			$params['page'] = $this->currentWikiTitle;
		}

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			// page param must not contain underscores
			if ( $key === 'page' && !$broken ) {
				$value = str_replace( '_', ' ', $value );
			}

			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';
		if ( $broken ) {
			$wikiText .= $this->getBrokenMacroCategory();
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$textNode = $node->ownerDocument->createTextNode( $wikiText );

		$node->parentNode->replaceChild( $textNode, $node );
	}
}
