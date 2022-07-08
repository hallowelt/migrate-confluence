<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class ImagePageLinkHelper {

	/**
	 * @var ConversionDataLookup
	 */
	protected $dataLookup;

	/**
	 * @var int
	 */
	protected $currentSpaceId;

	/**
	 * @var string
	 */
	protected $rawPageTitle;

	/**
	 * @var boolean
	 */
	protected $nsFileRepoCompat = false;

	/**
	 * @var boolean
	 */
	private $isBrokenLink = false;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( ConversionDataLookup $dataLookup,
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
			}
			$spaceId = $this->ensureSpaceId( $page );

			$confluencePageKey = $this->generatePageConfluenceKey( $spaceId, $this->rawPageTitle );

			$targetTitle = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
			if ( !empty( $targetTitle ) ) {
				return $targetTitle;
			} else {
				$this->isBrokenLink = true;
				// If not in migation data, save some info for manual post migration work
				return $this->generateConfluenceKey( $spaceId, $this->rawPageTitle );
			}
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
		return "$spaceId---$rawPageTitle";
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		return "Confluence---$spaceId---$rawPageTitle";
	}
}
