<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;

class PageLinkProcessor extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'page';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		if ( $node instanceof DOMElement ) {
			$rawPageTitle = $node->getAttribute( 'ri:content-title' );
			$spaceId = $this->ensureSpaceId( $node );

			$confluencePageKey = $this->generatePageConfluenceKey( $spaceId, $rawPageTitle );

			$targetTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
			if ( empty( $targetTitle ) ) {
				// If not in migation data, save some info for manual post migration work
				$linkParts[] = $this->generateConfluenceKey( $spaceId, $rawPageTitle );
			} else {
				$linkParts[] = $targetTitle;
			}

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->getPageLinkReplacement( $linkParts );
			}

			$this->replaceLink( $node, $replacement );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return int
	 */
	private function ensureSpaceId( DOMNode $node ): int {
		$spaceId = $this->currentSpaceId;
		$spaceKey = $node->getAttribute( 'ri:space-key' );

		if ( !empty( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		return $spaceId;
	}

	/**
	 * @param integer $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generatePageConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		return  "$spaceId---$rawPageTitle";
	}

	/**
	 * @param integer $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		return  "Confluence---$spaceId---$rawPageTitle";
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	private function getPageLinkReplacement( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		// Sometimes it could be that no label is set
		if ( count( $linkParts ) > 1 ) {
			$replacement = '[[' . implode( '|', $linkParts ) . ']]';
		} else {
			$labelParts = explode( ':', $linkParts[0] );
			$label = array_pop( $labelParts );
			$replacement = '[[' . $linkParts[0] . '|' . $label . ']]';
		}

		return $replacement;
	}

}
