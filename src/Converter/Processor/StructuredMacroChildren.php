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
		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}
			$name = $paramNode->getAttribute( 'ac:name' );
			if ( $name === 'page' ) {
				$params[$name] = $this->currentPageTitle;
				continue;
			}
			$params[$name] = $paramNode->nodeValue;
		}

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= '|' . $key . '=' . $value;
		}

		// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
		$text = $node->ownerDocument->createTextNode( '{{SubpageList' . $templateParams . '}}' );

		$node->parentNode->replaceChild( $text, $node );
	}
}
