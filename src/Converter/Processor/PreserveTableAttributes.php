<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * Apparently `pandoc` eats up all attributes of a table. So e. g.
 *
 * `<table data-layout="full-width" class="someotherclass">`
 *
 * becomes
 *
 * `{|`
 *
 * instead of
 *
 * `{| data-layout="full-width" class="someotherclass"`
 *
 * Therefore we preserve the information in the DOM and restore it in the post processing.
 * @see HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreTableAttributes
 */
class PreserveTableAttributes implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$tables = $dom->getElementsByTagName( 'table' );
		/** @var DOMElement $table */
		foreach ( $tables as $table ) {
			$rowContainer = $table;
			$tbody = $table->getElementsByTagName( 'tbody' )->item( 0 );
			if ( $tbody instanceof DOMElement ) {
				$rowContainer = $tbody;
			}

			$attributes = [];
			if ( $table->hasAttributes() ) {
				foreach ( $table->attributes as $attr ) {
					$name = $attr->nodeName;
					$value = $attr->nodeValue;
					$attributes[$name] = $value;
				}
			}

			$attributes = $this->ensureWikiTableClass( $attributes );

			if ( !empty( $attributes ) ) {
				$newRow = $dom->createElement( 'tr' );
				$newCell = $dom->createElement( 'td' );
				$newSpan = $dom->createElement( 'span' );
				$newSpanContent = $dom->createTextNode( '###PRESERVEDTABLEATTRIBUTES###' );
				foreach ( $attributes as $attrName => $attrValue ) {
					$newSpan->setAttribute( $attrName, $attrValue );
				}
				$newSpan->appendChild( $newSpanContent );
				$newCell->appendChild( $newSpan );
				$newRow->appendChild( $newCell );
				if ( $rowContainer->firstChild instanceof DOMNode ) {
					$rowContainer->insertBefore( $newRow, $rowContainer->firstChild );
				} else {
					$rowContainer->appendChild( $newRow );
				}
			}
		}
	}

	/**
	 *
	 * @param array $attributes
	 * @return array
	 */
	private function ensureWikiTableClass( $attributes ) {
		$newAttributes = [];
		$noClass = true;
		foreach ( $attributes as $name => $value ) {
			if ( $name === 'class' ) {
				$noClass = false;
				$classes = explode( ' ', $value );
				if ( !in_array( 'wikitable', $classes ) ) {
					$classes[] = 'wikitable';
				}
				$value = implode( ' ', $classes );
			}
			$newAttributes[$name] = $value;
		}

		if ( $noClass ) {
			$newAttributes['class'] = 'wikitable';
		}

		return $newAttributes;
	}
}
