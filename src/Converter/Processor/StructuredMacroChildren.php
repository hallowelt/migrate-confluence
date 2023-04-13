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

		$params = [];
		$depth = false;
		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}
			$name = $paramNode->getAttribute( 'ac:name' );
			if ( $name === 'depth' ) {
				$depth = (int)$paramNode->nodeValue;
			}
			if ( $name === 'page' ) {
				$params[$name] = $this->currentPageTitle;
				continue;
			}
			$params[$name] = $paramNode->nodeValue;
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$div = $node->ownerDocument->createElement( 'div' );
		if ( $depth !== false ) {
			$div->setAttribute( 'class', 'subpagelist subpagelist-depth-' . $depth );

			$templateParams = '';
			foreach ( $params as $key => $value ) {
				$templateParams .= '|' . $key . '=' . $value;
			}

			$div->appendChild(
				$node->ownerDocument->createTextNode(
					'{{SubpageList' . $templateParams . '}}'
				)
			);
			$node->parentNode->replaceChild( $div, $node );
		} else {
			$div->setAttribute( 'class', 'subpagelist no-depth' );
			if ( !empty( $params ) ) {
				$div->setAttribute( 'data-params', json_encode( $params ) );
			} else {
				$div->setAttribute( 'data-params', '{}' );
			}
			$div->appendChild(
				$node->ownerDocument->createTextNode(
					'[[Category:Broken_macro/' . $this->getMacroName() . ']]'
				)
			);
			$node->parentNode->replaceChild( $div, $node );
		}
	}
}
