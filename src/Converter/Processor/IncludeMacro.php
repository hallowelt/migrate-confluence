<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
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

	/**
	 * @var string
	 */
	protected string $mediaWikiPageName = '';

	/**
	 * @var DOMElement|null
	 */
	protected ?DOMElement $currentNode = null;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 */
	public function __construct( DBConversionDataLookup $dataLookup, int $currentSpaceId ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
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
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->currentNode = $node;
		$this->setMediaWikiPageName();

		$wikiTextTemplateCall = $this->makeTemplateCall();

		if ( $this->mediaWikiPageName === '' ) {
			$category = $this->getCategoryBrokenMacro( 'Include' );
			$wikiTextTemplateCall .= $category;
		}

		$wikiTextTemplateCallNode = $this->createTextNode(
			$node->ownerDocument,
			$wikiTextTemplateCall,
			__METHOD__
		);
		$node->parentNode->replaceChild( $wikiTextTemplateCallNode, $node );
	}

	/**
	 * @return string
	 */
	protected function makeTemplateCall(): string {
		return '{{:' . $this->mediaWikiPageName . '}}';
	}

	/**
	 * @return void
	 */
	private function setMediaWikiPageName(): void {
		if ( $this->currentNode instanceof DOMElement === false ) {
			return;
		}
		$pageEl = $this->currentNode->getElementsByTagName( 'page' )->item( 0 );
		if ( $pageEl === null ) {
			return;
		}
		$targetSpaceId = $this->currentSpaceId;
		$spaceKey = $pageEl->getAttribute( 'ri:space-key' );
		if ( $spaceKey !== '' ) {
			$targetSpaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey ) ?? 0;
		}
		$targetPageName = $pageEl->getAttribute( 'ri:content-title' );
		$this->mediaWikiPageName = $this->dataLookup->getPageTitleForLink(
			$this->currentSpaceId,
			$targetSpaceId,
			$targetPageName
		) ?? '';
	}
}
