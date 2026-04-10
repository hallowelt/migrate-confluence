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
	 *
	 * Pandoc strips unknown HTML elements like <excerpt-block> when converting to MediaWiki
	 * format. To preserve the tag, we insert text placeholders around the content here and
	 * restore the actual <excerpt-block> tag in the RestoreExcerptBlock postprocessor.
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

		$openTag = $node->ownerDocument->createTextNode(
			"#####EXCERPTBLOCKOPEN name=\"$macroId\" hidden=\"$hidden\"#####"
		);
		$parent->insertBefore( $openTag, $node );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $childNode->childNodes ) as $bodyChild ) {
					$parent->insertBefore( $bodyChild->cloneNode( true ), $node );
				}
			}
		}

		$closeTag = $node->ownerDocument->createTextNode( '#####EXCERPTBLOCKCLOSE#####' );
		$parent->insertBefore( $closeTag, $node );

		$brokenCategory = $node->ownerDocument->createTextNode(
			$this->getBrokenMacroCategory()
		);
		$parent->insertBefore( $brokenCategory, $node );

		$parent->removeChild( $node );
	}
}
