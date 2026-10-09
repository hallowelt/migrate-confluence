<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the Stiltsoft "Table Excerpt Include" macro (Table Filter, Charts & Spreadsheets)
 * to a BlueSpice <excerpt-include>.
 *
 * Unlike the native excerpt-include, the excerpt is identified by its name. The page is only
 * the "excerpt source" and is omitted in storage format when the source is "Current page".
 * This processor normalizes the macro so that ExcerptIncludeMacro can handle it:
 * - an explicit page reference (<ri:page> in any parameter) is moved to the default parameter
 * - without a page reference, the current page is used as default parameter
 * - the panel is always suppressed, as Table Excerpt Include renders no panel
 *
 * Not supported: multi-excerption (page trees, labels), "merge-tables" and "transpose".
 *
 * @see https://docs.stiltsoft.com/spaces/TFAC/pages/42241623/Table+Excerpt+and+Table+Excerpt+Include
 */
class TableExcerptIncludeMacro extends ExcerptIncludeMacro {

	public const MACRO_NAME = 'table-excerpt-include';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public function __construct(
		DBConversionDataLookup $dataLookup,
		int $currentSpaceId,
		PlaceholderManager $placeholderManager,
		private readonly ?string $currentConfluencePageTitle
	) {
		parent::__construct( $dataLookup, $currentSpaceId, $placeholderManager );
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->normalizePageParameter( $node );
		$this->setParameter( $node, 'nopanel', 'true' );

		parent::doProcessMacro( $node );
	}

	/**
	 * Makes sure the target page is in the default parameter (ac:name=""),
	 * which is where ExcerptIncludeMacro expects it.
	 *
	 * @param DOMElement $node
	 *
	 * @return void
	 */
	private function normalizePageParameter( DOMElement $node ): void {
		if ( $node->getElementsByTagName( 'default-parameter' )->length > 0 ) {
			return;
		}

		$pageReferenceParameter = null;
		foreach ( $this->getParameters( $node ) as $parameter ) {
			if ( $parameter->getAttribute( 'ac:name' ) === '' ) {
				return;
			}
			if ( !$pageReferenceParameter && $parameter->getElementsByTagName( 'page' )->length > 0 ) {
				$pageReferenceParameter = $parameter;
			}
		}

		// Excerpt source "Specific page"
		if ( $pageReferenceParameter ) {
			$pageReferenceParameter->setAttribute( 'ac:name', '' );
			return;
		}

		// Excerpt source "Current page". Without a title the parent marks the macro as broken.
		if ( !empty( $this->currentConfluencePageTitle ) ) {
			$this->setParameter( $node, '', $this->currentConfluencePageTitle );
		}
	}

	/**
	 * @param DOMElement $node
	 * @param string $name
	 * @param string $value
	 *
	 * @return void
	 */
	private function setParameter( DOMElement $node, string $name, string $value ): void {
		foreach ( $this->getParameters( $node ) as $parameter ) {
			if ( $parameter->getAttribute( 'ac:name' ) === $name ) {
				$parameter->textContent = $value;
				return;
			}
		}

		$document = $node->ownerDocument;
		$namespaceUri = $node->namespaceURI ?? $node->lookupNamespaceURI( 'ac' );
		if ( $namespaceUri ) {
			$parameter = $document->createElementNS( $namespaceUri, 'ac:parameter' );
			$parameter->setAttributeNS( $namespaceUri, 'ac:name', $name );
		} else {
			$parameter = $document->createElement( 'ac:parameter' );
			$parameter->setAttribute( 'ac:name', $name );
		}
		$parameter->appendChild( $document->createTextNode( $value ) );
		$node->insertBefore( $parameter, $node->firstChild );
	}

	/**
	 * Direct parameters of the macro only, not those of nested macros.
	 *
	 * @param DOMElement $node
	 *
	 * @return DOMElement[]
	 */
	private function getParameters( DOMElement $node ): array {
		$parameters = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement && $childNode->localName === 'parameter' ) {
				$parameters[] = $childNode;
			}
		}

		return $parameters;
	}
}
