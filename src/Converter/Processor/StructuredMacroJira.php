<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\WikiText\Template;

class StructuredMacroJira extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'jira';
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
		return 'Jira';
	}

	/**
	 * @param \DOMElement $node
	 * @return array
	 */
	protected function readParams( \DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				$paramValue = $childNode->nodeValue;
				$params[$paramName] = $paramValue;
			}
		}
		return $params;
	}

}
