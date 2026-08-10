<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use Exception;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class IncludeMacro extends StructuredMacroProcessorBase {

	/**
	 * @var DBConversionDataLookup
	 */
	protected DBConversionDataLookup $dataLookup;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	private ConversionHelper $conversionHelper;

	private bool $isBroken;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 */
	public function __construct( DBConversionDataLookup $dataLookup, int $currentSpaceId ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->conversionHelper = new ConversionHelper();
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'include';
	}

	/**
	 * @inheritDoc
	 * @throws \Exception
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->isBroken = false;
		$replacement = '';

		$wikiTitle = $this->getWikiPageTitle( $node );
		if ( !$wikiTitle ) {
			$this->isBroken = true;
		} else {
			$replacement = "{{:" . $wikiTitle . "}}";
		}

		if ( $this->isBroken ) {
			$replacement .= $this->getCategoryBrokenMacro( 'Include' );
		}

		$node->parentNode->replaceChild( $this->createTextNode(
			$node->ownerDocument,
			$replacement,
			__METHOD__
		), $node );
	}

	/**
	 * @throws Exception
	 */
	private function getWikiPageTitle( DOMElement $node ): ?string {
		$pageEl = $node->getElementsByTagName( 'page' )->item( 0 );
		if ( $pageEl === null ) {
			return null;
		}
		$targetPageName = $pageEl->getAttribute( 'ri:content-title' );

		$spaceId = null;
		$spaceKey = $node->getAttribute( 'ri:space-key' );

		if ( !empty( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		if ( !$spaceId ) {
			$spaceId = $this->currentSpaceId;
		}

		$wikiTitle = $this->dataLookup->getWikiPageTitleFromSpaceId(
			$this->currentSpaceId,
			$targetPageName
		);

		if ( $wikiTitle ) {
			return $wikiTitle;
		}

		// Fallback to confluence page key
		$this->isBroken = true;
		if ( empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $spaceId, $targetPageName );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $spaceKey, $targetPageName );
	}
}
