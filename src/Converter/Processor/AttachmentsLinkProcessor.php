<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;

class AttachmentsLinkProcessor extends LinkProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getProcessableNodeName(): string {
		return 'attachment';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessLink( DOMNode $node ): void {
		if ( $node instanceof DOMElement ) {
			$riFilename = $node->getAttribute( 'ri:filename' );
			$spaceId = $this->ensureSpaceId( $node );

			$nestedPageEl = $node->getElementsByTagName( 'page' )->item( 0 );

			$rawPageTitle = $this->rawPageTitle;
			if ( $nestedPageEl instanceof DOMElement ) {
				$rawPageTitle = $nestedPageEl->getAttribute( 'ri:content-title' );
			}
			$rawPageTitle = basename( $rawPageTitle );

			$confluenceFileKey = $this->generateConfluenceKey( $spaceId, $rawPageTitle, $riFilename );
			var_dump( $confluenceFileKey );
			$targetFilename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );
			var_dump( $targetFilename );
			if ( !empty( $targetFilename ) ) {
				$linkParts[] = $targetFilename;
			} else {
				$linkParts[] = $riFilename;
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
	 * @param integer $spaceId
	 * @param string $rawPageTitle
	 * @param string $filename
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle, string $filename ): string {
		return  "$spaceId---$rawPageTitle---$filename";
	}

	/**
	 * @param array $linkParts
	 * @return string
	 */
	private function getPageLinkReplacement( array $linkParts ): string {
		/*
		* The converter only knows the context of the current page that
		* is being converted
		* So unfortunately we don't know the source in this context so we
		* need to delegate this to the main migration script that has
		* all the information from the original XML
		*/
		$linkParts = array_map( 'trim', $linkParts );

		var_dump( $linkParts );
		if ( $this->nsFileRepoCompat ) {
			$filename = $linkParts[0];

			$pos = strpos( $filename, '_' );
			if ( $pos !== false ) {
				$namespace = substr( $filename, 0, $pos );
				if ( $namespace !== false ) {
					$linkParts[0] = str_replace( $namespace . '_', $namespace . ':', $filename );
				}
			}
		}
		return '[[Media:' . implode( '|', $linkParts ) . ']]';
	}
}
