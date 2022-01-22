<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\ConfluenceConverter;

class Layout implements \HalloWelt\MigrateConfluence\Converter\IProcessable {

	/**
	 *
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @return void
	 */
	public function process( $sender, $match, $dom, $xpath ): void {
		$replacement = '[[Category:Broken_layout]]';

		$match->parentNode->replaceChild(
			$dom->createTextNode( $replacement ),
			$match
		);
	}
}
