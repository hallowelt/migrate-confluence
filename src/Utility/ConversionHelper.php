<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMDocument;
use DOMException;
use DOMNode;

class ConversionHelper {

	/**
	 * @param string $macroName
	 * @return string
	 */
	public function getCategoryBrokenMacro( string $macroName ): string {
		return $this->getCategoryBroken( "macro/$macroName" );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function getCategoryBroken( string $name ): string {
		$type = str_replace( ' ', '_', $name );
		return "[[Category:Broken_$name]]";
	}

	/**
	 * @param DOMDocument $dom
	 * @param string $text
	 * @param string $caller
	 * @return DOMNode
	 *
	 * @throws DOMException
	 */
	protected function createTextNode(
		DOMDocument $dom, string $text, string $caller
	): DOMNode {
		if ( $dom instanceof DOMDocument === false ) {
			new DOMException(
				"Trying to createTextNode on invalid DOMDocument in " . $caller
			);
		}
		$textNode = $dom->createTextNode( $text );
		if ( $textNode instanceof DOMNode === false ) {
			new DOMException(
				"createTextNode does not return DOMNode in " . $caller
			);
		}
		return $textNode;
	}
}
