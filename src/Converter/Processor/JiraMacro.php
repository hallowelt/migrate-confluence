<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MediaWiki\Lib\WikiText\Template;

class JiraMacro extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'jira';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$params = $this->readParams( $node );

		$hasKey = isset( $params['key'] ) && $params['key'] !== '';
		$hasJql = isset( $params['jql'] ) && $params['jql'] !== '';

		if ( !$hasKey && !$hasJql ) {
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( $this->getBrokenMacroCategory() ),
				$node
			);
			return;
		}

		// Prefer key over JQL since we can produce a direct issue link for it.
		if ( $hasKey ) {
			unset( $params['jql'] );
		}

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
	 * @param DOMNode $node
	 * @return array
	 */
	protected function readParams( DOMNode $node ): array {
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
