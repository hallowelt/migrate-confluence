<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MediaWiki\Lib\WikiText\Template;

class SpaceDetailsMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'space-details';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
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
	 * @param DOMNode $node
	 * @return array
	 */
	protected function readParams( DOMNode $node ): array {
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
