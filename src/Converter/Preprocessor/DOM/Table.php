<?php

namespace HalloWelt\MigrateConfluence\Converter\Preprocessor\DOM;

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
			$this->migrateCellFeatures( $table );
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
	 * Translate "ac:local-id" to "id"
	 *
	 * @param DOMElement $table
	 * @return void
	 */
	private function translateId( DOMElement $table ): void {
		$nonLiveList = [ $table ];
		array_push( $nonLiveList, ...$table->getElementsByTagName( 'tr' ) );
		array_push( $nonLiveList, ...$table->getElementsByTagName( 'th' ) );
		array_push( $nonLiveList, ...$table->getElementsByTagName( 'td' ) );

		foreach ( $nonLiveList as $element ) {
			if ( !$element->hasAttribute( 'ac:local-id' ) ) {
				continue;
			}
			$id = $element->getAttribute( 'ac:local-id' );

			$element->setAttribute( 'id', $id );
			$element->removeAttribute( 'ac:local-id' );
		}
	}

	/**
	 * migrate styling features of table cells
	 *
	 * @param DOMElement $table
	 * @return void
	 */
	private function migrateCellFeatures( DOMElement $table ): void {
		$nonLiveList = [];
		array_push( $nonLiveList, ...$table->getElementsByTagName( 'th' ) );
		array_push( $nonLiveList, ...$table->getElementsByTagName( 'td' ) );

		foreach ( $nonLiveList as $element ) {
			if ( !$element->hasAttribute( 'data-highlight-colour' ) ) {
				continue;
			}
			$style = $element->getAttribute( 'style' );
			if ( strpos( $style, 'background-color' ) === false ) {
				if ( $style && !str_ends_with( $style, ';' ) ) {
					$style .= ';';
				}
				$element->setAttribute(
					'style',
					$style . 'background-color: ' .
						$this->mapColorNameToRGB( $element->getAttribute( 'data-highlight-colour' ) ) );
				$element->removeAttribute( 'data-highlight-colour' );
			}
		}
	}

	private function mapColorNameToRGB( string $colorName ): string {
		$colorMap = [
			'gray' => '#f0f1f2',
			'grey' => '#f0f1f2',
			'blue' => '#cfe1fd',
			'teal' => '#c6edfb',
			'green' => '#baf3db',
			'yellow' => '#f5e989',
			'red' => '#ffd5d2',
			'purple' => '#eed7fc',
		];

		return $colorMap[ strtolower( $colorName ) ] ?? $colorName;
	}
}
