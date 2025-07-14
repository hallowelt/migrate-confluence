<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class StructuredMacroInclude extends StructuredMacroProcessorBase {

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
	protected $mediaWikiPageName = '';

	/**
	 * @var DOMNode
	 */
	protected $currentNode = null;

	/**
	 * @param ConversionDataLookup $dataLookup
	 */
	public function __construct( ConversionDataLookup $dataLookup, int $currentSpaceId ) {
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
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$this->currentNode = $node;
		$this->setMediaWikiPageName();
		if ( $this->mediaWikiPageName === '' ) {
			return;
		}
		$wikiTextTemplateCall = $this->makeTemplateCall();
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
	 */
	private function setMediaWikiPageName(): void {
		$pageEl = $this->currentNode->getElementsByTagName( 'page' )->item( 0 );
		if ( $pageEl === null ) {
			return;
		}
		$targetPageName = $pageEl->getAttribute( 'ri:content-title' );
		$confluencePageKey = $this->generatePageConfluenceKey( $this->currentSpaceId, $targetPageName );
		$this->mediaWikiPageName = $this->dataLookup->getTargetTitleFromConfluencePageKey( $confluencePageKey );
	}

	/**
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string
	 */
	private function generatePageConfluenceKey( int $spaceId, string $rawPageTitle ): string {
		$genericTitleBuilder = new GenericTitleBuilder( [] );
			$rawPageTitle = $genericTitleBuilder
				->appendTitleSegment( $rawPageTitle )->build();
			$rawPageTitle = str_replace( ' ', '_', $rawPageTitle );
		return "$spaceId---$rawPageTitle";
	}
}
