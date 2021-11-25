<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;

use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Excerpt implements \HalloWelt\MigrateConfluence\Converter\IProcessable {
	/**
	 * @inheritDoc
	 */
	public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath ): void {
		$oNewContainer = $dom->createElement( 'div' );
		$oNewContainer->setAttribute( 'class', 'ac-excerpt' );

		// TODO: reflect modes "INLINE" and "BLOCK"
		//See https://confluence.atlassian.com/doc/excerpt-macro-148062.html

		$match->parentNode->insertBefore( $oNewContainer, $match );

		$oRTBody = $xpath->query( './ac:rich-text-body', $match )->item( 0 );
		// Move all content out of <ac::rich-text-body>
		while ( $oRTBody->childNodes->length > 0 ) {
			$oChild = $oRTBody->childNodes->item( 0 );
			$oNewContainer->appendChild( $oChild );
		}
	}
}
