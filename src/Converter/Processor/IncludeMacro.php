<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
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
	 *
	 * @param DOMElement $node
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->currentNode = $node;
		$this->setMediaWikiPageName();

		$wikiTextTemplateCall = $this->makeTemplateCall();

		if ( $this->mediaWikiPageName === '' ) {
			$category = "[[Category:Broken_macro/Include]]";
			$wikiTextTemplateCall .= $category;
		}

		$wikiTextTemplateCallNode = $node->ownerDocument->createTextNode( $wikiTextTemplateCall );
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
	 * @throws InvalidTitleException
	 */
	private function setMediaWikiPageName(): void {
		$pageEl = $this->currentNode->getElementsByTagName( 'page' )->item( 0 );
		if ( !( $pageEl instanceof DOMElement ) ) {
			return;
		}
		$targetPageName = $pageEl->getAttribute( 'ri:content-title' );
		$this->mediaWikiPageName = $this->dataLookup->getWikiPageTitleFromSpaceId(
			$this->currentSpaceId,
			$targetPageName
		) ?? '';
	}
}
