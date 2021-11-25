<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities\Macros;

use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class InlineCommentMarker implements \HalloWelt\MigrateConfluence\Converter\IProcessable {

	/**
	 * {@inheritDoc}
	 * <ac:inline-comment-marker ac:ref="ca3f84d8-5618-4cdb-b8f6-b58f4e29864e">
	 *	Alternatives
	 * </ac:inline-comment-marker>
	 */
	public function process( ?ConfluenceConverter $sender, \DOMNode $match, \DOMDocument $dom, \DOMXPath $xpath ): void {
		$wikiText = "{{InlineComment|{$match->nodeValue}}}";
		$match->parentNode->replaceChild(
			$dom->createTextNode( $wikiText ),
			$match
		);
	}
}
