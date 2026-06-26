<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use DOMNode;
use PHPUnit\Framework\TestCase;

abstract class ProcessorTestCase extends TestCase {

	/**
	 * Asserts that two DOMDocuments are equal, ignoring whitespace-only text nodes
	 * (e.g. blank lines or indentation between elements).
	 *
	 * @param DOMDocument $expected
	 * @param DOMDocument $actual
	 * @return void
	 */
	protected function assertDomXmlEquals( DOMDocument $expected, DOMDocument $actual ): void {
		$this->assertEquals(
			$this->canonicalize( $expected ),
			$this->canonicalize( $actual )
		);
	}

	/**
	 * Returns a normalized XML string with whitespace-only text nodes removed.
	 *
	 * @param DOMDocument $dom
	 * @return string
	 */
	private function canonicalize( DOMDocument $dom ): string {
		/** @var DOMDocument $clone */
		$clone = $dom->cloneNode( true );
		// Merge adjacent text nodes before stripping (the processor may create
		// multiple sibling text nodes that the XML parser merges on reload).
		$clone->normalize();
		$this->stripWhitespaceNodes( $clone );
		return $clone->saveXML();
	}

	/**
	 * Recursively normalises text nodes: removes whitespace-only ones and trims
	 * leading/trailing whitespace from mixed-content ones (e.g. indented template strings).
	 *
	 * @param DOMNode $node
	 * @return void
	 */
	private function stripWhitespaceNodes( DOMNode $node ): void {
		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			if ( $child->nodeType === XML_TEXT_NODE ) {
				$trimmed = trim( $child->nodeValue );
				if ( $trimmed === '' ) {
					$node->removeChild( $child );
				} else {
					$child->nodeValue = $trimmed;
				}
			} else {
				$this->stripWhitespaceNodes( $child );
			}
		}
	}
}
