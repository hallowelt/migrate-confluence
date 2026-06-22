<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;

class AttachmentLink extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'attachment';
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	protected function doProcessLink( DOMElement $node ): void {
		$riFilename = $node->getAttribute( 'ri:filename' );
		$spaceId = $this->ensureSpaceId( $node ) ?? 0;
		// TODO: Log if spaceId is null, but we should be able to
		// resolve the filename without spaceId as well, so we can continue processing

		$nestedPageEl = $node->getElementsByTagName( 'page' )->item( 0 );

		$rawPageTitle = $this->rawPageTitle;
		if ( $nestedPageEl instanceof DOMElement ) {
			$rawPageTitle = $nestedPageEl->getAttribute( 'ri:content-title' );
		}

		$filenameResolver = new FilenameResolver( $this->dataLookup, $this->migrationConfig );
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenLink ] =
			$filenameResolver->resolve( $spaceId, $rawPageTitle, $riFilename );

		$linkParts = [ $targetFilename ];
		$this->getLinkBody( $node, $linkParts );

		$replacement = $this->getBrokenLinkReplacement();

		if ( !empty( $linkParts ) ) {
			$replacement = $this->makeLink( $linkParts );
		}

		if ( $isBrokenLink ) {
			$replacement .= $this->getCategoryBroken( 'attachment_link' );
		}

		$this->replaceLink( $node, $replacement );
	}

	/**
	 * @param DOMElement $node
	 * @return int|null
	 */
	private function ensureSpaceId( DOMElement $node ): ?int {
		$spaceId = $this->currentSpaceId;
		$pageNode = $node->getElementsByTagName( 'page' )->item( 0 );

		if ( !$pageNode ) {
			return $spaceId;
		}
		$spaceKey = $pageNode->getAttribute( 'ri:space-key' );

		if ( !empty( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		return $spaceId;
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	public function makeLink( array $linkParts ): string {
		/*
		* The converter only knows the context of the current page that
		* is being converted
		* So unfortunately we don't know the source in this context so we
		* need to delegate this to the main migration script that has
		* all the information from the original XML
		*/
		$linkParts = array_map( 'trim', $linkParts );

		return '[[Media:' . implode( '|', $linkParts ) . ']]';
	}
}
