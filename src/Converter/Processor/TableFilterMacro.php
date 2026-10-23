<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="table-filter" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="inverse">,</ac:parameter>
 *	 <ac:parameter ac:name="sparkName">...</ac:parameter>
 *	 <ac:parameter ac:name="column">...</ac:parameter>
 *	 <ac:parameter ac:name="limitHeight" />
 *	 <ac:parameter ac:name="separator">...</ac:parameter>
 *	 <ac:parameter ac:name="labels">...</ac:parameter>
 *	 <ac:parameter ac:name="default">...</ac:parameter>
 *	 <ac:parameter ac:name="isFirstTimeEnter">...</ac:parameter>
 *	 <ac:parameter ac:name="cell-width">...</ac:parameter>
 *	 <ac:parameter ac:name="userfilter">...</ac:parameter>
 *	 <ac:parameter ac:name="datepattern">dd.mm.yy</ac:parameter>
 *	 <ac:parameter ac:name="id">...</ac:parameter>
 *	 <ac:parameter ac:name="updateSelectOptions">...</ac:parameter>
 *	 <ac:parameter ac:name="worklog">365|5|8|y w d h m|y w d h m</ac:parameter>
 *	 <ac:parameter ac:name="isOR">AND</ac:parameter>
 *	 <ac:parameter ac:name="order">0,1</ac:parameter>
 *	 <ac:rich-text-body>
 *     ...
 *   </ac:rich-text-body>
 * </ac:structured-macro>
 *
 * The table is marked as "datagrid" (TableTools) and all parameters are kept in "data-filterparams".
 * Only two parameters have an effect: "disabled" (="true") leaves the table without the "datagrid"
 * class, and a numeric "cell-width" becomes a "min-width" style on all cells.
 */
class TableFilterMacro implements IProcessor {

	public const MACRO_NAME = 'table-filter';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$actualMacros = [];
		foreach ( $dom->getElementsByTagName( 'structured-macro' ) as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === static::MACRO_NAME ) {
				$actualMacros[] = $macro;
			}
		}

		foreach ( $actualMacros as $actualMacro ) {
			$parentNode = $actualMacro->parentNode;

			$params = [];
			foreach ( $actualMacro->childNodes as $child ) {
				if ( !( $child instanceof DOMElement ) || $child->localName !== 'parameter' ) {
					continue;
				}
				$paramName = $child->getAttribute( 'ac:name' );
				if ( trim( $paramName ) === '' ) {
					continue;
				}
				$params[$paramName] = $child->nodeValue;
			}
			// A disabled filter leaves a plain table, which TableTools does not turn into a data grid
			$isDisabled = strtolower( trim( $params['disabled'] ?? '' ) ) === 'true';
			$cellWidth = $this->parseCellWidth( $params['cell-width'] ?? '' );
			$filterParams = json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			foreach ( iterator_to_array( $actualMacro->childNodes ) as $child ) {
				if ( !( $child instanceof DOMElement ) || $child->localName !== 'rich-text-body' ) {
					continue;
				}
				foreach ( iterator_to_array( $child->childNodes ) as $bodyChild ) {
					if ( $bodyChild instanceof DOMElement && $bodyChild->nodeName === 'table' ) {
						if ( !$isDisabled ) {
							$classes = [];
							if ( $bodyChild->hasAttribute( 'class' ) ) {
								$classes = explode( ' ', $bodyChild->getAttribute( 'class' ) );
							}
							$classes[] = 'datagrid';
							$bodyChild->setAttribute( 'class', implode( ' ', $classes ) );
						}
						$bodyChild->setAttribute( 'data-filterparams', $filterParams );
						if ( $cellWidth !== null ) {
							$this->applyCellWidth( $bodyChild, $cellWidth );
						}
					}
					if ( $this->isEmptyParagraph( $bodyChild ) ) {
						continue;
					}
					$parentNode->insertBefore( $bodyChild, $actualMacro );
				}
			}

			$parentNode->removeChild( $actualMacro );
		}
	}

	/**
	 * Returns the width in pixels, or null if the value is not a plain number (e.g. empty or ",").
	 */
	private function parseCellWidth( string $value ): ?int {
		if ( !preg_match( '/^\s*(\d+)\s*(px)?\s*$/i', $value, $matches ) || (int)$matches[1] === 0 ) {
			return null;
		}
		return (int)$matches[1];
	}

	/**
	 * Keeps the data grid cells at least as wide as configured; TableTools preserves cell styles.
	 */
	private function applyCellWidth( DOMElement $table, int $width ): void {
		foreach ( $table->getElementsByTagName( '*' ) as $cell ) {
			if ( $cell->nodeName !== 'th' && $cell->nodeName !== 'td' ) {
				continue;
			}
			$style = rtrim( trim( $cell->getAttribute( 'style' ) ), ';' );
			$style = $style === '' ? '' : "$style; ";
			$cell->setAttribute( 'style', "{$style}min-width: {$width}px;" );
		}
	}

	/**
	 * Detects the editor's empty cursor-target paragraphs around the table.
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	private function isEmptyParagraph( DOMNode $node ): bool {
		return $node instanceof DOMElement && $node->nodeName === 'p'
			&& trim( $node->textContent ) === ''
			&& $node->getElementsByTagName( '*' )->length === $node->getElementsByTagName( 'br' )->length;
	}
}
