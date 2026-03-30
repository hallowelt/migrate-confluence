<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class ChildrenMacro extends StructuredMacroProcessorBase {

	/**
	 * @var int
	 */
	private $spaceId = -1;

	/**
	 * @var string
	 */
	private $currentPageTitle = '';

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @param int $spaceId
	 * @param string $currentPageTitle
	 * @param ConversionDataLookup $dataLookup
	 */
	public function __construct( int $spaceId, string $currentPageTitle, ConversionDataLookup $dataLookup ) {
		$this->spaceId = $spaceId;
		$this->currentPageTitle = $currentPageTitle;
		$this->dataLookup = $dataLookup;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'children';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
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
								if ( $pageLink->hasAttribute( 'ri:space-key' ) ) {
									$spaceKey = $pageLink->getAttribute( 'ri:space-key' );
									$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
								}

								// Get confluence page title if set
								if ( $pageLink->hasAttribute( 'ri:content-title' ) ) {
									$pageConfluenceTitle = $pageLink->getAttribute( 'ri:content-title' );
									$pageConfluenceTitle = str_replace( ' ', '_', $pageConfluenceTitle );
									if ( $pageConfluenceTitle === '' ) {
										// If no page title can be found mark macro as broken
										$broken = true;
										$params[$name] = "Confluence---{$spaceId}---{$pageConfluenceTitle}";
										break;
									}

									$wikiTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey(
										"{$spaceId}---{$pageConfluenceTitle}"
									);

									$params[$name] = $wikiTitle;

									if ( $wikiTitle === '' ) {
										// If wiki page title is empty mark macro as broken
										$params[$name] = "Confluence---{$spaceId}---{$pageConfluenceTitle}";
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
			$params['page'] = $this->currentPageTitle;
		}

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		$wikiText = '{{SubpageList' . $templateParams . '}}';
		if ( $broken ) {
			$wikiText .= $this->getBrokenMacroCategroy();
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$textNode = $node->ownerDocument->createTextNode( $wikiText );

		$node->parentNode->replaceChild( $textNode, $node );
	}
}
