<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

abstract class LinkProcessorBase implements IProcessor {

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
	 * @var DOMNode
	 */
	private $linkNode;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param bool $nsFileRepoCompat
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle, bool $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->nsFileRepoCompat = $nsFileRepoCompat;
	}

	/**
	 * @return string
	 */
	abstract protected function getProcessableNodeName(): string;

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	abstract protected function doProcessLink( DOMNode $node ): void;

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$processableNodeName = $this->getProcessableNodeName();

		$processableLiveNodes = $dom->getElementsByTagName( $processableNodeName );

		$processableNodes = [];
		foreach ( $processableLiveNodes as $processableLiveNode ) {
			$processableNodes[] = $processableLiveNode;
		}

		foreach ( $processableNodes as $processableNode ) {
			$this->setLinkNode( $processableNode );
			$this->doProcessLink( $processableNode );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	private function setLinkNode( $node ): void {
		$this->linkNode = $node->parentNode;
	}

	/**
	 * @return DOMNode
	 */
	protected function getLinkNode(): DOMNode {
		return $this->linkNode;
	}

	/**
	 * @return string
	 */
	protected function getBrokenLinkReplacement(): string {
		return '[[Category:Broken_link]]';
	}

	/**
	 * @param DOMNode $node
	 * @param array &$linkParts
	 * @return void
	 */
	protected function getLinkBody( $node, &$linkParts ): void {
		// Let's see if there is a description Text
		// HTML Content
		$linkBodys = $node->parentNode->getElementsByTagName( 'link-body' );
		$linkBody = $linkBodys->item( 0 );

		if ( $linkBody instanceof DOMElement === false ) {
			// CDATA Content
			$linkBodys = $node->parentNode->getElementsByTagName( 'plain-text-link-body' );
			$linkBody = $linkBodys->item( 0 );
		}

		if ( $linkBody instanceof DOMElement ) {
			$linkParts[] = $linkBody->nodeValue;
		}
	}

	/**
	 * @param DOMNode $node
	 * @param string $replacement
	 * @return void
	 */
	protected function replaceLink( DOMNode $node, string $replacement ): void {
		$linkNode = $this->getLinkNode();

		$linkNode->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $replacement ),
			$linkNode
		);
	}
}
