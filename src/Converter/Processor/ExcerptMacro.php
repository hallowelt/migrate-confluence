<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

/**
 * Converts the Confluence excerpt macro to a BlueSpice <excerpt-block> element.
 * The broken macro category is added because the BlueSpice Excerpt extension is not yet available.
 *
 * @see https://confluence.atlassian.com/doc/excerpt-macro-148062.html
 */
class ExcerptMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'excerpt';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$macroId = $node->getAttribute( 'ac:macro-id' );
		$hidden = 'false';

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === 'hidden' ) {
				$hidden = trim( $childNode->nodeValue );
				break;
			}
		}

		$excerptBlock = $node->ownerDocument->createElement( 'excerpt-block' );
		$excerptBlock->setAttribute( 'name', $macroId );
		$excerptBlock->setAttribute( 'hidden', $hidden );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $childNode->childNodes ) as $bodyChild ) {
					$excerptBlock->appendChild( $bodyChild->cloneNode( true ) );
				}
			}
		}

		$node->parentNode->replaceChild( $excerptBlock, $node );

		$brokenCategory = $excerptBlock->ownerDocument->createTextNode(
			$this->getBrokenMacroCategory()
		);
		$excerptBlock->parentNode->insertBefore( $brokenCategory, $excerptBlock->nextSibling );
	}
}
