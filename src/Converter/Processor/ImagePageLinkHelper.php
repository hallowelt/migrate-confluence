<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReader;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

class ImagePageLinkHelper extends ConversionHelper {

	/**
	 * @var bool
	 */
	private bool $isBrokenLink = false;

	/** @var string */
	private string $spaceKey = '';

	/**
	 * @param IConverterDataReader $reader
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct(
		protected IConverterDataReader $reader,
		protected int $currentSpaceId,
		protected string $rawPageTitle
	) {
	}

	/**
	 * @param DOMElement $node
	 * @return string
	 */
	public function getLinkTarget( DOMElement $node ): string {
		if ( $node->nodeName === 'ac:link' ) {
			$sourceSpaceId = $this->currentSpaceId;
			$page = $node->getElementsByTagName( 'page' )->item( 0 );

			if ( $page instanceof DOMElement ) {
				$this->rawPageTitle = $page->getAttribute( 'ri:content-title' );
				$this->currentSpaceId = $this->ensureSpaceId( $page );
			}

			$targetTitle = $this->reader->getWikiPageTitleForLink(
				$sourceSpaceId,
				$this->currentSpaceId,
				$this->rawPageTitle
			);
			if ( $targetTitle !== null ) {
				return $targetTitle;
			}
			$this->isBrokenLink = true;
			// If not in migration data, save some info for manual post migration work
			return $this->generateConfluenceKey( $this->currentSpaceId, $this->rawPageTitle );
		}

		return '';
	}

	/**
	 * @return bool
	 */
	public function isBrokenLink(): bool {
		return $this->isBrokenLink;
	}

	/**
	 * @param DOMElement $node
	 * @return int
	 */
	private function ensureSpaceId( DOMElement $node ): int {
		$spaceId = $this->currentSpaceId;
		$this->spaceKey = $node->getAttribute( 'ri:space-key' );
		if ( !empty( $this->spaceKey ) ) {
			$spaceId = $this->reader->getSpaceIdFromSpaceKey( $this->spaceKey ) ?? 0;
			// TODO: Log if spaceId is null, but we should be able to
			// resolve the filename without spaceId as well, so we can continue processing
		} else {
			$spaceId = $this->currentSpaceId;
			$this->spaceKey = $this->reader->getSpaceKeyFromSpaceId( $spaceId );
		}

		return $spaceId;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		if ( !empty( $this->spaceKey ) ) {
			return $this->getConfluencePageKeyFromSpaceKey( $this->spaceKey, $rawPageTitle );
		}
		return $this->getConfluencePageKeyFromSpaceId( $spaceId, $rawPageTitle );
	}
}
