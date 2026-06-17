<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;

class PageLink extends LinkProcessorBase {

	/** @var string */
	private string $spaceKey = '';

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'page';
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return void
	 * @throws InvalidTitleException
	 */
	protected function doProcessLink( DOMElement $node ): void {
		if ( !( $node instanceof DOMElement ) ) {
			return;
		}

		$isBrokenLink = false;
		$rawPageTitle = $node->getAttribute( 'ri:content-title' );
		$spaceId = $this->ensureSpaceId( $node );

		$targetTitle = $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $rawPageTitle );
		if ( $targetTitle === null ) {
			// If not in migration data, save some info for manual post migration work
			$targetTitle = $this->generateConfluenceKey( $spaceId, $rawPageTitle );
			$isBrokenLink = true;
		}

		$linkParts = [ $targetTitle ];
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

	/**
	 * @param DOMElement $node
	 *
	 * @return int
	 */
	private function ensureSpaceId( DOMElement $node ): int {
		$spaceId = $this->currentSpaceId;
		$this->spaceKey = $node->getAttribute( 'ri:space-key' );

		if ( !empty( $this->spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $this->spaceKey ) ?? 0;
			// TODO: Log if spaceId is null,
			// but we should be able to resolve the filename without spaceId as well,
			// so we can continue processing
		}

		return $spaceId;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return string
	 * @throws InvalidTitleException
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		if ( !empty( $rawPageTitle ) ) {
			$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder->appendTitleSegment( $rawPageTitle )->build();
		}

		$confluenceKey = "Confluence---$spaceId---$rawPageTitle";
		if ( $this->spaceKey !== '' ) {
			$confluenceKey = "Confluence---$this->spaceKey---$rawPageTitle";
		}

		return str_replace( ' ', '_', $confluenceKey );
	}

	/**
	 * @param array $linkParts
	 *
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
