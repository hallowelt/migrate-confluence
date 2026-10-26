<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMElement;

/**
 * Fills a <pageconstraints> element as understood by the PageConstraints extension.
 */
class PageConstraintsXml {

	/**
	 * Row used for content without restrictions.
	 */
	public const NONE = [ 'type' => 'none', 'timestamp' => '', 'targets' => [] ];

	/**
	 * Row used if no stored constraints were found: nobody but the targets listed may do anything.
	 */
	public const FAIL_CLOSED = [ 'type' => 'read_and_edit', 'timestamp' => '', 'targets' => [] ];

	/**
	 * @param DOMElement $element Empty element created in the target document
	 * @param array{type: string, timestamp: string, targets: array} $row
	 */
	public static function fill( DOMElement $element, array $row ): void {
		$element->setAttribute( 'type', $row['type'] );
		$element->setAttribute( 'inherit', '0' );
		if ( $row['timestamp'] !== '' ) {
			$element->setAttribute( 'timestamp', $row['timestamp'] );
		}
		foreach ( $row['targets'] as $target ) {
			$targetEl = $element->ownerDocument->createElement( 'target' );
			$targetEl->setAttribute( 'kind', $target['kind'] );
			$targetEl->setAttribute( 'name', $target['name'] );
			$targetEl->setAttribute( 'level', $target['level'] );
			$element->appendChild( $targetEl );
		}
	}
}
