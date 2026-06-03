<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ImagePageLinkHelper {

	/**
	 * @var DBConversionDataLookup
	 */
	protected DBConversionDataLookup $dataLookup;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	/**
	 * @var string
	 */
	protected string $rawPageTitle;

	/**
	 * @var bool
	 */
	private bool $isBrokenLink = false;

	/** @var string */
	private string $spaceKey = '';

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( DBConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public function getLinkTarget( DOMNode $node ): string {
		if ( $node instanceof DOMElement && $node->nodeName === 'ac:link' ) {
			$page = $node->getElementsByTagName( 'page' )->item( 0 );

			if ( $page instanceof DOMElement ) {
				$this->rawPageTitle = $page->getAttribute( 'ri:content-title' );
				$this->currentSpaceId = $this->ensureSpaceId( $page );
			}

			$targetTitle = $this->dataLookup->getTargetWikiTitleFromSpaceId(
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
	 * @param DOMNode $node
	 * @return int
	 */
	private function ensureSpaceId( DOMNode $node ): int {
		$spaceId = $this->currentSpaceId;
		$this->spaceKey = $node->getAttribute( 'ri:space-key' );
		if ( !empty( $this->spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $this->spaceKey );
		}

		return $spaceId;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		$confluenceKey = "Confluence---$spaceId---$rawPageTitle";
		if ( $this->spaceKey !== '' ) {
			$confluenceKey = "Confluence---$this->spaceKey---$rawPageTitle";
		}
		return $confluenceKey;
	}
}
