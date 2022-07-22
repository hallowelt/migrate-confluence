<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroChildren extends StructuredMacroProcessorBase {

	/**
	 * @var string
	 */
	private $currentPageTitle = '';

	/**
	 * @param string $currentPageTitle
	 */
	public function __construct( string $currentPageTitle ) {
		$this->currentPageTitle = $currentPageTitle;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'children';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$paramNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramNodes[] = $childNode;
			}
		}

		$depth = false;
		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}
			$name = $paramNode->getAttribute( 'ac:name' );
			if ( $name === 'depth' ) {
				$depth = (int)$paramNode->nodeValue;
				break;
			}
		}

		if ( $depth !== false ) {
			// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
			$div = $node->ownerDocument->createElement( 'div' );
			$div->setAttribute( 'class', 'subpagelist subpagelist-depth-' . $depth );
			$div->appendChild(
				$node->ownerDocument->createTextNode(
					'{{SubpageList|page=' . $this->currentPageTitle . '|depth=' . $depth . '}}'
				)
			);
			$node->parentNode->insertBefore( $div, $node );
		}
	}
}
