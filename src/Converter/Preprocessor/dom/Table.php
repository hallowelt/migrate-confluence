<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\dom;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IDomPreprocessor;

/**
 * Pandoc is removing whole table structure if a colgroup
 * section is part of the table
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
}
