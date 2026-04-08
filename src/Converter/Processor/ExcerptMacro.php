<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

/**
 * Converts the Confluence excerpt macro to {{ExcerptStart|name=...|hidden=...}}content{{ExcerptEnd}}.
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

		$parent = $node->parentNode;

		$openTemplate = $node->ownerDocument->createTextNode(
			"{{ExcerptStart###BREAK###\n|name = $macroId###BREAK###\n|hidden = $hidden###BREAK###\n}}"
		);
		$parent->insertBefore( $openTemplate, $node );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $childNode->childNodes ) as $bodyChild ) {
					$parent->insertBefore( $bodyChild->cloneNode( true ), $node );
				}
			}
		}

		$closeTemplate = $node->ownerDocument->createTextNode( '{{ExcerptEnd}}' );
		$parent->insertBefore( $closeTemplate, $node );

		$parent->removeChild( $node );
	}
}
