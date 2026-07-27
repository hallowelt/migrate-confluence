<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

/**
 * Converts the Confluence excerpt macro to a BlueSpice <excerpt-block> element.
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
	 * Placeholders use pipe-separated values to avoid HTML attribute quote encoding issues.
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$hidden = 'false';
		$excerptName = "";

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === 'hidden' ) {
				$hidden = trim( $childNode->nodeValue );
			}
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === 'name' ) {
				$excerptName = trim( $childNode->nodeValue );
			}
		}

		$parent = $node->parentNode;

		$openTag = $this->createTextNode(
			$node->ownerDocument,
			"#####EXCERPTBLOCKOPEN|$excerptName|$hidden#####",
			__METHOD__
		);
		$parent->insertBefore( $openTag, $node );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				foreach ( iterator_to_array( $childNode->childNodes ) as $bodyChild ) {
					$parent->insertBefore( $bodyChild->cloneNode( true ), $node );
				}
			}
		}

		$closeTag = $this->createTextNode(
			$node->ownerDocument,
			'#####EXCERPTBLOCKCLOSE#####',
			__METHOD__
		);
		$parent->insertBefore( $closeTag, $node );

		$parent->removeChild( $node );
	}
}
