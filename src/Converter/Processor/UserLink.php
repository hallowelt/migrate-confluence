<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

class UserLink extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'user';
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	protected function doProcessLink( DOMElement $node ): void {
		$linkParts = [];

		$isBrokenLink = false;
		$userKey = $node->getAttribute( 'ri:userkey' );

		if ( !empty( $userKey ) ) {
			$username = $this->dataLookup->getUsernameFromUserKey( $userKey ) ?? $userKey;
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
			$replacement .= $this->getCategoryBroken( 'user_link' );
		}

		$this->replaceLink( $node, $replacement );
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
