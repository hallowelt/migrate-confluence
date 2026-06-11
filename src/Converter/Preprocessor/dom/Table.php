<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\dom;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;

/**
 * Pandoc is removing whole table structure if a colgroup
 * section is part of the.
 * And we want to translate "ac:local-id" to "id"
 */
class Table implements IDomPreprocessor {

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	public function preprocess( DOMDocument $dom ): void {
		$tables = $dom->getElementsByTagName( 'table' );

		$nonLiveList = [];
		foreach ( $tables as $table ) {
			$nonLiveList[] = $table;
		}

		foreach ( $nonLiveList as $table ) {
			if ( $table instanceof DOMElement === false ) {
				continue;
			}

			$this->removeColgroup( $table );
			$this->translateId( $table );
		}
	}

	/**
	 * Remove colgroup section from table to prevent pandoc
	 * from removing the whole table
	 *
	 * @param DOMElement $table
	 * @return void
	 */
	private function removeColgroup( DOMElement $table ): void {
		foreach ( $table->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'colgroup' ) {
				$table->removeChild( $childNode );
			}
		}
	}

	/**
	 * Translate "ac:local-id" to "id" to "id"
	 *
	 * @param DOMElement $table
	 * @return void
	 */
	private function translateId( DOMElement $table ): void {
		$nonLiveList = [];

		// Table rows (tr)
		$trEls = $table->getElementsByTagName( 'tr' );
		foreach( $trEls as $trEl ) {
			$nonLiveList[] = $trEl;
		}

		// Table heads (th)
		$thEls = $table->getElementsByTagName( 'th' );
		foreach( $thEls as $thEl ) {
			$nonLiveList[] = $thEl;
		}

		// Table cells (td)
		$tdEls = $table->getElementsByTagName( 'td' );
		foreach( $tdEls as $tdEl ) {
			$nonLiveList[] = $tdEl;
		}

		foreach ( $nonLiveList as $element ) {
			if ( !$element->hasAttribute( 'ac:local-id' ) ) {
				continue;
			}
			$id = $element->getAttribute( 'ac:local-id' );

			$element->setAttribute( 'id', $id );
			$element->removeAttribute( 'ac:local-id' );
		}
	}
}
