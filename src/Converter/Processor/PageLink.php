<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class PageLink extends LinkProcessorBase {

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
			$isBrokenLink = false;
			$rawPageTitle = $node->getAttribute( 'ri:content-title' );
			$spaceId = $this->ensureSpaceId( $node );

			$confluencePageKey = $this->generatePageConfluenceKey( $spaceId, $rawPageTitle );

			var_dump( $confluencePageKey );

			$targetTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
			var_dump( $targetTitle );
			if ( !empty( $targetTitle ) ) {
				$linkParts[] = $targetTitle;
			} else {
				// If not in migation data, save some info for manual post migration work
				$linkParts[] = $this->generateConfluenceKey( $spaceId, $rawPageTitle );
				$isBrokenLink = true;
			}

			var_dump( $linkParts );
			var_dump( '---' );

			$this->getLinkBody( $node, $linkParts );

			$replacement = $this->getBrokenLinkReplacement();

			if ( !empty( $linkParts ) ) {
				$replacement = $this->makeLink( $linkParts );
			}

			if ( $isBrokenLink ) {
				$replacement .= '[[Category:Broken_page_link]]';
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
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generatePageConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder
				->appendTitleSegment( $rawPageTitle )->build();
			$rawPageTitle = str_replace( ' ', '_', $rawPageTitle );
		return "$spaceId---$rawPageTitle";
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
			$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder
				->appendTitleSegment( $rawPageTitle )->build();
			$rawPageTitle = str_replace( ' ', '_', $rawPageTitle );
		return "Confluence---$spaceId---$rawPageTitle";
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	public function makeLink( array $linkParts ): string {
		$linkParts = array_map( 'trim', $linkParts );

		// Sometimes it could be that no label is set
		if ( count( $linkParts ) > 1 ) {
			$replacement = '[[' . implode( '|', $linkParts ) . ']]';
		} else {
			$titleParts = explode( ':', $linkParts[0] );
			$label = array_pop( $titleParts );
			$labelParts = explode( '/', $label );
			$label = array_pop( $labelParts );
			$label = str_replace( '_', ' ', $label );

			$replacement = '[[' . $linkParts[0] . '|' . $label . ']]';
		}

		return $replacement;
	}

}
