<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNodeList;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 * <ac:structured-macro ac:name="info" ac:schema-version="1" ac:macro-id="448329ba-06ad-4845-b3bf-2fd9a75c0d51">
 *	<ac:parameter ac:name="title">/api/Device/devices</ac:parameter>
 *	<ac:rich-text-body>
 *		<p class="title">...</p>
 *		<p>...</p>
 *	</ac:rich-text-body>
 * </ac:structured-macro>
 */
abstract class ConvertMacroToTemplateBase implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );
		$requiredMacroName = $this->getMacroName();
		$wikiTextTemplateName = $this->getWikiTextTemplateName();

		// Collect all DOMElements in a non-live list
		$actualMacros = [];
		foreach ( $macros as $macro ) {
			$macroName = $macro->getAttribute( 'ac:name' );
			if ( $macroName !== $requiredMacroName ) {
				continue;
			}
			$actualMacros[] = $macro;
		}

		foreach ( $actualMacros as $actualMacro ) {
			$parentNode = $actualMacro->parentNode;
			$openTemplate = "{{" . "$wikiTextTemplateName";
			if ( $this->addLinebreakInsideTemplate() ) {
				$openTemplate .= "###BREAK###\n";
			}
			$wikitextTemplateStartTextNode = $dom->createTextNode(
				$openTemplate
			);
			$parentNode->insertBefore( $wikitextTemplateStartTextNode, $actualMacro );

			// Extract scalar parameters
			$parameterEls = $actualMacro->getElementsByTagName( 'parameter' );
			foreach ( $parameterEls as $parameterEl ) {
				$paramName = $parameterEl->getAttribute( 'ac:name' );
				if ( trim( $paramName ) === '' ) {
					continue;
				}
				$paramValue = $parameterEl->nodeValue;
				// We add a "###BREAK###", as `pandoc` will eat up regular line breaks.
				// They will be restored in a "Postprocessor"
				$praramString = " |$paramName = $paramValue";
				if ( $this->addLinebreakInsideTemplate() ) {
					$praramString .= "###BREAK###\n";
				}
				$paramTextNode = $dom->createTextNode( $praramString );
				$parentNode->insertBefore( $paramTextNode, $actualMacro );
			}

			// Extract rich text bodies
			/** @var DOMNodeList $richTextBodies */
			$richTextBodies = $actualMacro->getElementsByTagName( 'rich-text-body' );
			$richTextBodyEls = [];
			foreach ( $richTextBodies as $richTextBody ) {
				$richTextBodyEls[] = $richTextBody;
			}

			if ( !empty( $richTextBodyEls ) ) {
				$bodyString = " |body = ";
				if ( $this->addLinebreakInsideTemplate() ) {
					$bodyString .= "###BREAK###\n";
				}
				$wikitextTemplateEndTextNode = $dom->createTextNode( $bodyString );
				$parentNode->insertBefore( $wikitextTemplateEndTextNode, $actualMacro );

				foreach ( $richTextBodyEls as $richTextBodyEl ) {
					// For some odd reason, iterating `$richTextBodyEl->childNodes` directly
					// will give children of `$dom->firstChild`.
					// Using `iterator_to_array` as an workaround here.
					$childNodes = iterator_to_array( $richTextBodyEl->childNodes );
					foreach ( $childNodes as $richTextBodyChildEl ) {
						if ( $richTextBodyChildEl === $actualMacro ) {
							continue;
						}
						$parentNode->insertBefore( $richTextBodyChildEl, $actualMacro );
					}
				}
			}

			$closeTemplate = "}}";
			if ( $this->addLinebreakAfterTemplate() ) {
				$closeTemplate .= "###BREAK###\n";
			}
			$wikitextTemplateEndTextNode = $dom->createTextNode( "$closeTemplate" );
			$parentNode->insertBefore( $wikitextTemplateEndTextNode, $actualMacro );

			$parentNode->removeChild( $actualMacro );
		}
	}

	/**
	 *
	 * @return string
	 */
	abstract protected function getMacroName(): string;

	/**
	 *
	 * @return string
	 */
	abstract protected function getWikiTextTemplateName(): string;

	/**
	 * @return bool
	 */
	protected function addLinebreakInsideTemplate(): bool {
		return true;
	}

	/**
	 * @return bool
	 */
	protected function addLinebreakAfterTemplate(): bool {
		return true;
	}
}
