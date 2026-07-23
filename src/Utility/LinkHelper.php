<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMElement;
use Exception;

class LinkHelper {

	/** @var ConversionHelper */
	private ConversionHelper $conversionHelper;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 */
	public function __construct(
		private readonly DBConversionDataLookup $dataLookup,
		private readonly int $currentSpaceId
	) {
		$this->conversionHelper = new ConversionHelper();
	}

	/**
	 * @param DOMElement $node
	 *
	 * Returns existing wiki title from
	 * <ac:link>
	 *   <ri:page ri:space-key="ABC" ri:content-title="Some Confluence page name" ri:version-at-save="2"/>
	 * </ac:link>
	 * or
	 * <ri:page ri:space-key="ABC" ri:content-title="Some Confluence page name" ri:version-at-save="2"/>
	 *
	 * @return string|null
	 * @throws Exception
	 */
	public function getWikiPageTitleFromLinkElement( DomElement $node ): ?string {
		$page = $this->findPageNode( $node );
		if ( !$page ) {
			return null;
		}

		$spaceId = $this->ensureSpaceId( $page );
		if ( !$spaceId ) {
			return null;
		}

		$rawPageTitle = $page->getAttribute( 'ri:content-title' );

		$targetTitle = $this->dataLookup->getWikiPageTitleFromSpaceId(
			$spaceId,
			$rawPageTitle
		);
		if ( !$targetTitle ) {
			return null;
		}

		return $targetTitle;
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 *
	 * @return string
	 */
	public function generateConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		$spaceKey = $this->dataLookup->getSpaceKeyFromSpaceId( $spaceId );
		if ( !empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $spaceKey, $rawPageTitle );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $spaceId, $rawPageTitle );
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return int|null
	 */
	public function ensureSpaceId( DOMElement $node ): ?int {
		$spaceKey = $node->getAttribute( 'ri:space-key' );
		if ( empty ( $spaceKey ) ) {
			return $this->currentSpaceId;
		}

		return $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return DOMElement|\DOMNameSpaceNode|\DOMNode|null
	 */
	private function findPageNode( DOMElement $node ) {
		if ( $node->nodeName === 'ri:page' ) {
			return $node;
		}

		if ( $node->nodeName !== 'ac:link' ) {
			return null;
		}

		$page = $node->getElementsByTagName( 'page' )->item( 0 );
		if ( $page instanceof DOMElement ) {
			return null;
		}

		return $page;
	}

}
