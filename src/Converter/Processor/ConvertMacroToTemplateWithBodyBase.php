<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
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
abstract class ConvertMacroToTemplateWithBodyBase implements IProcessor {

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $dom->getElementsByTagName( 'structured-macro' );
		$requiredMacroName = $this->getMacroName();
		$templateStartName = $this->getWikiTextTemplateStartName();
		$templateEndName = $this->getWikiTextTemplateEndName();

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

			// Extract scalar parameters — only direct children, not those inside nested macros.
			$parameterEls = [];
			foreach ( $actualMacro->childNodes as $child ) {
				if ( $child instanceof DOMElement && $child->localName === 'parameter' ) {
					$parameterEls[] = $child;
				}
			}

			$openTemplate = "{{" . "$templateStartName";
			if ( $this->addLinebreakInsideTemplate() && count( $parameterEls ) > 0 ) {
				$openTemplate .= "###BREAK###\n";
			}
			$wikitextTemplateStartTextNode = $dom->createTextNode(
				$openTemplate
			);
			$parentNode->insertBefore( $wikitextTemplateStartTextNode, $actualMacro );

			// Extract scalar parameters
			foreach ( $parameterEls as $parameterEl ) {
				$paramName = $parameterEl->getAttribute( 'ac:name' );
				if ( trim( $paramName ) === '' ) {
					continue;
				}
				$paramValue = $parameterEl->nodeValue;
				// We add a "###BREAK###", as `pandoc` will eat up regular line breaks.
				// They will be restored in a "PostProcessor"
				$paramString = "|$paramName = $paramValue";
				if ( $this->addLinebreakInsideTemplate() ) {
					$paramString .= "###BREAK###\n";
				}
				$paramTextNode = $dom->createTextNode( $paramString );
				$parentNode->insertBefore( $paramTextNode, $actualMacro );
			}

			// close opening template
			$paramTextNode = $dom->createTextNode( "}}" );
			$parentNode->insertBefore( $paramTextNode, $actualMacro );

			$this->extractBodyElements( $actualMacro, $parentNode );

			// add closing template
			$wikitextTemplateEndTextNode = $dom->createTextNode( "{{" . $templateEndName . "}}" );
			$parentNode->insertBefore( $wikitextTemplateEndTextNode, $actualMacro );

			$parentNode->removeChild( $actualMacro );
		}
	}

	/**
	 * Extract rich text bodies
	 *
	 * @param DOMElement $actualMacro
	 * @param DOMElement $parentNode
	 * @return void
	 */
	protected function extractBodyElements( DOMElement $actualMacro, DOMElement $parentNode ): void {
		// Only collect the direct rich-text-body child, not those from nested macros.
		$richTextBodyEls = [];
		foreach ( $actualMacro->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->localName === 'rich-text-body' ) {
				$richTextBodyEls[] = $child;
			}
		}

		if ( !empty( $richTextBodyEls ) ) {
			$bodyString = "";

			$wikitextTemplateEndTextNode = $actualMacro->ownerDocument->createTextNode( $bodyString );
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
	abstract protected function getWikiTextTemplateStartName(): string;

	/**
	 *
	 * @return string
	 */
	abstract protected function getWikiTextTemplateEndName(): string;

	/**
	 *
	 * @return bool
	 */
	protected function addLinebreakInsideTemplate(): bool {
		return true;
	}
}
