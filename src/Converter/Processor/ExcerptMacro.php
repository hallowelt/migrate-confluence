<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;

/**
 * MediaWiki (without PageExcerpts extension) has no equivalent for excerpts.
 * The macro is therefore marked as broken and its body content is kept in place,
 * so no information is lost.
 *
 * @see https://confluence.atlassian.com/doc/excerpt-macro-148062.html
 * @see https://docs.atlassian.com/DAC/javadoc/confluence/4.0/reference/com/atlassian/confluence/macro/Macro.OutputType.html
 */
class ExcerptMacro extends StructuredMacroProcessorBase {

	public function __construct(
		private readonly IConverterDataWriter $writer,
		private readonly int $currentSpaceId
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'excerpt';
	}

	/**
	 * Is broken per default
	 *
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$hidden = '';
		$excerptName = '';
		$layout = 'block';
		$richTextBody = null;

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}

			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				$richTextBody = $childNode;
				continue;
			}

			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			$paramName = $childNode->getAttribute( 'ac:name' );
			$paramValue = trim( $childNode->nodeValue );

			if ( $paramName === 'hidden' ) {
				$hidden = $paramValue;
			}

			if ( $paramName === 'name' ) {
				$excerptName = $paramValue;
			}

			if ( $paramName === 'atlassian-macro-output-type' ) {
				$layout = strtolower( $paramValue );
			}
		}

		$params = [ "type=$layout" ];
		if ( $hidden !== '' ) {
			$params[] = "hidden = $hidden";
		}
		if ( $excerptName !== '' ) {
			$params[] = "excerpt = $excerptName";
		}

		$replacement = '{{Excerpt|' . implode( '|', $params ) . '}}' . $this->getBrokenMacroCategory();

		$parentNode = $node->parentNode;

		$parentNode->insertBefore( $this->createTextNode(
			$node->ownerDocument,
			$replacement,
			__METHOD__
		), $node );

		if ( $richTextBody !== null ) {
			foreach ( iterator_to_array( $richTextBody->childNodes ) as $bodyChild ) {
				$parentNode->insertBefore( $bodyChild, $node );
			}
		}

		$parentNode->removeChild( $node );

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			"Excerpt"
		);
	}
}
