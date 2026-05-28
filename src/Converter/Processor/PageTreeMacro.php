<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

/**
 * Partially converting pagetree macro
 */
class PageTreeMacro extends StructuredMacroProcessorBase {

	/** @var array */
	private array $params = [];

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'pagetree';
	}

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpace
	 * @param string $currentWikiTitle
	 * @param string $mainpage
	 */
	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpace,
		private string $currentWikiTitle,
		private string $mainpage
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
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
			$template .= ' ' . $this->getBrokenMacroCategory();
		}

		$macroReplacement = $node->ownerDocument->createTextNode( $template );
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 *
	 * @param DOMNode $macro
	 *
	 * @return void
	 */
	private function macroParams( DOMNode $macro ): void {
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

		// if content-title is not set fallback to {{FULLPAGENAME}}
		if ( !isset( $this->params['content-title'] ) ) {
			$this->params['content-title'] = '{{FULLPAGENAME}}';

			if ( isset( $this->params['space-key'] ) ) {
				$namespace = $this->dataLookup->getSpacePrefixFromSpaceKey( $this->params['space-key'] );
				if ( is_string( $namespace ) ) {
					$this->params['space-key'] = $namespace;
				}
			}
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
	 *  Specify the page title or a special value as follows:
	 *  Your page title — to specify a page name for the parent or root of the tree.
	 *        The tree will include all children and grand-children of the specified root.
	 *        The tree will not include the specified root page itself.
	 *  '@home' — will include all pages under the home page of the space (default).
	 *  '@self' — will include all pages under the current page.
	 *  '@parent' — will include all pages under the parent of the current page, including the current page.
	 *  '@none' — will include all pages in the space, including orphaned pages and the home page.
	 *
	 *  See https://confluence.atlassian.com/conf59/page-tree-macro-792499177.html
	 *  Convert to https://github.com/ProfessionalWiki/SubPageList/blob/master/doc/USAGE.md
	 *
	 * @param array $params
	 *
	 * @return array
	 */
	private function translateRootPageParams( array $params ): array {
		if ( !isset( $params['content-title'] ) ) {
			return $params;
		}

		switch ( $params['content-title'] ) {
			case '@home':
				// Main Page
				$text = $this->dataLookup->getTargetWikiTitleFromSpaceId(
					$this->currentSpace,
					$this->mainpage
				);
				if ( $text === '' ) {
					$params['broken-macro'] = true;
					break;
				}
				$params['content-title'] = $text;
				break;
			case '@self':
				// current WikiTitle
				$text = $this->dataLookup->getTargetWikiTitleFromSpaceId(
					$this->currentSpace,
					$this->currentWikiTitle
				);
				if ( $text === '' ) {
					$params['broken-macro'] = true;
					break;
				}
				$params['content-title'] = $text;
				break;
			case '@parent':
				// parent of current PageTitle
				$currentWikiTitle = $this->dataLookup->getTargetWikiTitleFromSpaceId(
					$this->currentSpace,
					$this->currentWikiTitle
				);
				if ( $currentWikiTitle === '' ) {
					$params['broken-macro'] = true;
					break;
				}
				$currentPageParts = explode( '/', $currentWikiTitle );
				if ( count( $currentPageParts ) > 1 ) {
					array_pop( $currentPageParts );
					$text = implode( '/', $currentPageParts );
				} else {
					$text = $this->currentWikiTitle;
					$params['broken-macro'] = true;
				}
				$params['content-title'] = $text;
				break;
			case '@none':
				// all pages in namespace
				$params['content-title'] = '';

				if ( isset( $params['space-key'] ) ) {
					$namespace = $this->dataLookup->getNamepspaceFromSpaceKey( $params['space-key'] );
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
				$text = $this->dataLookup->getTargetWikiTitleFromSpaceId( $spaceId, $params['content-title'] );
				if ( $text === '' ) {
					$params['broken-macro'] = true;
					break;
				}
				if ( is_string( $text ) ) {
					$params['content-title'] = $text;
				}
				if ( isset( $params['space-key'] ) ) {
					$namespace = $this->dataLookup->getNamepspaceFromSpaceKey( $params['space-key'] );
					if ( is_string( $namespace ) ) {
						$params['space-key'] = $namespace;
					}
				}
				break;
		}

		return $params;
	}
}
