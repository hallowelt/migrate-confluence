<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

/**
 * Partially implements the Confluence "detailssummary" macro.
 *
 * A full implementation would require SMW property queries to replicate the
 * dynamic content pulled from Details-macro pages via the CQL filter.
 * For now we emit an empty table with the configured column headers and
 * preserve the original CQL expression as a visible note so the customer
 * knows what data was supposed to appear here.
 *
 * Input:
 * <ac:structured-macro ac:name="detailssummary" …>
 *   <ac:parameter ac:name="firstcolumn">Document</ac:parameter>
 *   <ac:parameter ac:name="headings">Status, Approved on, Approved by</ac:parameter>
 *   <ac:parameter ac:name="sortBy">Document</ac:parameter>
 *   <ac:parameter ac:name="cql">label = "support" and space = currentSpace()</ac:parameter>
 * </ac:structured-macro>
 *
 * Output: an HTML table whose header row contains firstcolumn + the comma-separated
 * headings, followed by an italic paragraph with the original CQL query.
 */
class DetailsSummaryMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'detailssummary';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->extractParams( $node );

		$dom = $node->ownerDocument;
		$parentNode = $node->parentNode;

		// --- Empty table with header row ---
		$table = $dom->createElement( 'table' );
		$table->setAttribute( 'class', 'wikitable' );

		$headerRow = $dom->createElement( 'tr' );

		$firstColumn = trim( $params['firstcolumn'] ?? '' );
		if ( $firstColumn !== '' ) {
			$th = $dom->createElement( 'th' );
			$th->appendChild( $dom->createTextNode( $firstColumn ) );
			$headerRow->appendChild( $th );
		}

		$headings = trim( $params['headings'] ?? '' );
		if ( $headings !== '' ) {
			foreach ( array_map( 'trim', explode( ',', $headings ) ) as $heading ) {
				if ( $heading === '' ) {
					continue;
				}
				$th = $dom->createElement( 'th' );
				$th->appendChild( $dom->createTextNode( $heading ) );
				$headerRow->appendChild( $th );
			}
		}

		$table->appendChild( $headerRow );
		$parentNode->insertBefore( $table, $node );

		// --- CQL note for the customer ---
		$cql = trim( $params['cql'] ?? '' );
		if ( $cql !== '' ) {
			$note = $dom->createElement( 'p' );
			$em = $dom->createElement( 'em' );
			$em->appendChild( $dom->createTextNode( 'Details Summary - CQL: ' . $cql ) );
			$note->appendChild( $em );
			$parentNode->insertBefore( $note, $node );
		}

		$parentNode->removeChild( $node );
	}

	/**
	 * @param DOMElement $node
	 * @return array<string, string>
	 */
	private function extractParams( DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $child ) {
			if ( !( $child instanceof DOMElement ) || $child->localName !== 'parameter' ) {
				continue;
			}
			$name = $child->getAttribute( 'ac:name' );
			if ( $name !== '' ) {
				$params[$name] = $child->nodeValue;
			}
		}
		return $params;
	}
}
