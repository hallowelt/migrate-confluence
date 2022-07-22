<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;

class UserLink extends LinkProcessorBase {

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
		$linkParts = [];

		if ( $node instanceof DOMElement ) {
			$isBrokenLink = false;
			$userKey = $node->getAttribute( 'ri:userkey' );

			if ( !empty( $userKey ) ) {
				$username = $this->dataLookup->getUsernameFromUserKey( $userKey );
				$linkParts[] = 'User:' . $username;
				$linkParts[] = $username;
			} else {
				$linkParts[] = 'NULL';
				$linkParts[] = 'NULL';
				$isBrokenLink = true;
			}

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->makeLink( $linkParts );
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
	public function makeLink( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );
		$linkBody = implode( '|', $linkParts );
		$replacement = '[[' . $linkBody . ']]';

		return $replacement;
	}
}
