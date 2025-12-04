<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\WikiText\Template;

class StructuredMacroSpaceDetails extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'space-details';
	}

	/**
	 * @param \DOMElement $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$params = $this->readParams( $node );
		$wikitextTemplate = new Template( $this->getWikiTextTemplateName(), $params );
		$wikitextTemplate->setRenderFormatted( false );
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode(
				$wikitextTemplate->render()
			),
			$node
		);
	}

	protected function getWikiTextTemplateName(): string {
		return 'SpaceDetails';
	}

	/**
	 * @param \DOMElement $node
	 * @return array
	 */
	protected function readParams( \DOMElement $node ): array {
		$params = [];
		$params['width'] = '100%';

		foreach ( $node->childNodes as $paramNode ) {
			if ( $paramNode->nodeName === 'ac:parameter' ) {
				$paramName = $paramNode->getAttribute( 'ac:name' );
				if ( $paramName === 'width' ) {
					if ( trim( $paramNode->nodeValue ) !== '' ) {
						$params['width'] = trim( $paramNode->nodeValue );
					}
				}
			}
		}
		return $params;
	}
}
