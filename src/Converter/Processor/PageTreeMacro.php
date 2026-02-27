<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

/**
 * Partially converting pagetree macro
 */
class PageTreeMacro extends StructuredMacroProcessorBase {

	/** @var ConversionDataLookup */
	private $dataLookup;

	/** @var int */
	private $currentSpace = 0;

	/** @var string */
	private $currentPageTitle = '';

	/** @var string */
	private $mainpage = 'Main Page';

	/** @var array */
	private $params = [];

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'pagetree';
	}

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpace
	 * @param string $currentPageTitle
	 * @param string $mainpage
	 */
	public function __construct(
		ConversionDataLookup $dataLookup, int $currentSpace, string $currentPageTitle, string $mainpage
	) {
		$this->dataLookup = $dataLookup;
		$this->currentSpace = $currentSpace;
		$this->currentPageTitle = $currentPageTitle;
		$this->mainpage = $mainpage;
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$this->macroParams( $node );
		$brokenMacro = false;
		if ( isset( $this->params['broken-macro'] ) ) {
			unset( $this->params['broken-macro'] );
			$brokenMacro = true;
		}

		ksort( $this->params );

		$template = "{{PageTree";
		foreach ( $this->params as $key => $value ) {
			$template .= "|$key=$value";
		}
		$template .= "}}";

		if ( $brokenMacro ) {
			$template .= ' ' . $this->getBrokenMacroCategroy();
		}

		$macroReplacement = $node->ownerDocument->createTextNode( $template );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMNode $macro
	 * @return void
	 */
	private function macroParams( $macro ): void {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === 'root' ) {
					// page link
					$rootParams = $this->extractRootPageParams( $childNode );
					if ( !empty( $rootParams ) ) {
						$params[$paramName] = $rootParams;
					}
				} else {
					$params[$paramName] = $childNode->nodeValue;
				}
			}
		}

		if ( isset( $params['root'] ) ) {
			$this->params = $this->translateRootPageParams( $params['root'] );
			$this->params = array_merge( $params, $this->params );
			unset( $this->params['root'] );
		}
	}

	/**
	 * @param DOMElement $node
	 * @return array
	 */
	private function extractRootPageParams( DOMElement $node ): array {
		$params = [];

		$pageNodes = $node->getElementsByTagName( 'page' );
		if ( count( $pageNodes ) > 0 ) {
			foreach ( $pageNodes[0]->attributes as $attribute ) {
				$params[$attribute->localName] = $attribute->nodeValue;
			}
		}

		return $params;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	private function translateRootPageParams( array $params ): array {
		if ( isset( $params['content-title'] ) ) {
			/**
			 * Specify the page title or a special value as follows:
			 * Your page title — to specify a page name for the parent or root of the tree.
			 * 		The tree will include all children and grand-children of the specified root.
			 *		The tree will not include the specified root page itself.
			 * '@home' — will include all pages under the home page of the space (default).
			 * '@self' — will include all pages under the current page.
			 * '@parent' — will include all pages under the parent of the current page, including the current page.
			 * '@none' — will include all pages in the space, including orphaned pages and the home page.
			 *
			 * See https://confluence.atlassian.com/conf59/page-tree-macro-792499177.html
			 * Convert to https://github.com/ProfessionalWiki/SubPageList/blob/master/doc/USAGE.md
			 */
			switch ( $params['content-title'] ) {
				case '@home':
					// Main Page
					$key = $this->getTitleLookupKey( $this->currentSpace, $this->mainpage );
					$text = $this->dataLookup->getTargetTitleFromConfluencePageKey( $key );
					if ( $text === '' ) {
						$params['broken-macro'] = true;
						break;
					}
					$params['content-title'] = $text;
					break;
				case '@self':
					// current PageTitle
					$key = $this->getTitleLookupKey( $this->currentSpace, $this->currentPageTitle );
					$text = $this->dataLookup->getTargetTitleFromConfluencePageKey( $key );
					if ( $text === '' ) {
						$params['broken-macro'] = true;
						break;
					}
					$params['content-title'] = $text;
					break;
				case '@parent':
					// parent of current PageTitle
					$key = $this->getTitleLookupKey( $this->currentSpace, $this->currentPageTitle );
					$currentPageTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $key );
					if ( $currentPageTitle === '' ) {
						$params['broken-macro'] = true;
						break;
					}
					$currentPageParts = explode( '/', $currentPageTitle );
					if ( count( $currentPageParts ) > 1 ) {
						$subpageTitle = array_pop( $currentPageParts );
						$text = array_pop( $currentPageParts );
					} else {
						$text = $this->currentPageTitle;
						$params['broken-macro'] = true;
					}
					$params['content-title'] = $text;
					break;
				case '@none':
					// all pages in namespace
					$params['content-title'] = '';

					if ( isset( $params['space-key'] ) ) {
						$namespace = $this->dataLookup->getSpacePrefixFromSpaceKey( $params['space-key'] );
						if ( is_string( $namespace ) ) {
							$params['space-key'] = $namespace;
						} else {
							$params['space-key'] = '{{NAMESPACE}}';
						}
					} else {
						$params['space-key'] = '{{NAMESPACE}}';
					}
					// always broken. Subpage tree based on namespace is not supported until now.
					$params['broken-macro'] = true;
					break;
				default:
					// create new content-title from space key and content title
					if ( isset( $params['space-key'] ) ) {
						$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $params['space-key'] );
					} else {
						$spaceId = $this->currentSpace;
					}
					$key = $this->getTitleLookupKey( $spaceId, $params['content-title'] );
					$text = $this->dataLookup->getTargetTitleFromConfluencePageKey( $key );
					if ( $text === '' ) {
						$params['broken-macro'] = true;
						break;
					}
					if ( is_string( $text ) ) {
						$params['content-title'] = $text;
					}
					$namespace = $this->dataLookup->getSpacePrefixFromSpaceKey( $params['space-key'] );
					if ( is_string( $namespace ) ) {
						$params['space-key'] = $namespace;
					}
					break;
			}
		} else {
			// if content-title is not set fallback to {{FULLPAGENAME}}
			$params['content-title'] = '{{FULLPAGENAME}}';
			if ( isset( $params['space-key'] ) ) {
				$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $params['space-key'] );
			} else {
				$spaceId = $this->currentSpace;
			}
			$namespace = $this->dataLookup->getSpacePrefixFromSpaceKey( $params['space-key'] );
			if ( is_string( $namespace ) ) {
				$params['space-key'] = $namespace;
			}
			$params['broken-macro'] = true;
		}
		return $params;
	}

	/**
	 * @param int $spaceId
	 * @param string $text
	 * @return string
	 */
	private function getTitleLookupKey( int $spaceId, string $text ): string {
		$rawText = basename( $text );
		return (string)$spaceId . '---' . $rawText;
	}
}
