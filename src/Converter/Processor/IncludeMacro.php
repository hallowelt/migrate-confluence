<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use Exception;
use HalloWelt\MigrateConfluence\Converter\DataReader\IConverterDataReader;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

class IncludeMacro extends StructuredMacroProcessorBase {

	/** @var ConversionHelper */
	private ConversionHelper $conversionHelper;

	/** @var bool */
	private bool $isBroken;

	/**
	 * @param IConverterDataReader $reader
	 * @param int $currentSpaceId
	 */
	public function __construct(
		protected IConverterDataReader $reader,
		protected int $currentSpaceId ) {
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

		$targetSpaceId = $this->currentSpaceId;
		$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
		if ( $spaceKey !== '' ) {
			$targetSpaceId = $this->reader->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
		}

		$targetPageName = $pageEl->getAttribute( 'ri:content-title' );

		$wikiTitle = $this->reader->getWikiPageTitleForLink(
			$this->currentSpaceId,
			$targetSpaceId,
			$targetPageName
		);

		if ( $wikiTitle ) {
			return $wikiTitle;
		}

		// Fallback to confluence page key
		$this->isBroken = true;
		if ( empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $targetSpaceId, $targetPageName );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $spaceKey, $targetPageName );
	}
}
