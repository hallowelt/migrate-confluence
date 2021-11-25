<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

class Layout implements \HalloWelt\MigrateConfluence\Converter\IProcessable {

	public function process( $sender, $match, $dom, $xpath ): void {
		$replacement = '[[Category:Broken_layout]]';

		$match->parentNode->replaceChild(
			$dom->createTextNode( $replacement ),
			$match
		);
	}
}
