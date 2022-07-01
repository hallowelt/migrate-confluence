<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;

class UserLinkProcessor extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'user';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		if ( $node instanceof DOMElement ) {
			$isBrokenLink = false;
			$userKey = $node->getAttribute( 'ri:userkey' );

			if ( !empty( $userKey ) ) {
				$linkParts[] = 'User:' . $userKey;
			} else {
				$linkParts[] = 'NULL';
				$isBrokenLink = true;
			}

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->getUserLinkReplacement( $linkParts );
			}

			if ( $isBrokenLink ) {
				$replacement .= '[[Category:Broken_user_link]]';
			}

			$this->replaceLink( $node, $replacement );
		}
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	private function getUserLinkReplacement( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		$labelParts = explode( ':', $linkParts[0] );
		$label = array_pop( $labelParts );
		$replacement = '[[' . $linkParts[0] . '|' . $label . ']]';

		return $replacement;
	}
}
