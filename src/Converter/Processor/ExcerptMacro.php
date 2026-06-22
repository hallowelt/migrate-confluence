<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

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
	 * Placeholders use pipe-separated values to avoid HTML attribute quote encoding issues.
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroId = $node->getAttribute( 'ac:macro-id' );
		$hidden = 'false';

		// TODO: Set to false as soon as the excerpt extension is available
		$isBroken = true;

		if ( empty( $macroId ) ) {
			$isBroken = true;
		}

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) === 'hidden' ) {
				$hidden = trim( $childNode->nodeValue );
				break;
			}
		}

		$parent = $node->parentNode;

		$openTag = $this->createTextNode(
			$node->ownerDocument,
			"#####EXCERPTBLOCKOPEN|$macroId|$hidden#####",
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

		if ( $isBroken ) {
			$brokenCategory = $this->createTextNode(
				$node->ownerDocument,
				$this->getBrokenMacroCategory(),
				__METHOD__
			);
			$parent->insertBefore( $brokenCategory, $node );
		}

		$parent->removeChild( $node );
	}
}
