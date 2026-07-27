<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMText;
use DOMXPath;

class LivesearchMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'livesearch';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$macros = $this->findAsStructuredMacro( $dom );

		$macroName = $this->getMacroName();

		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === $macroName ) {
				$this->doProcessMacro( $macro );
			}
		}

		$this->processAsWikiMarkup( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 *
	 * @return array
	 */
	private function findAsStructuredMacro( DOMDocument $dom ): array {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			$macros[] = $structuredMacro;
		}

		return $macros;
	}

	/**
	 * Rewrites `{macroName:a=1|b=2}` -> `{{TemplateName|a=1|b=2}}` and the
	 * bare `{macroName}` -> `{{TemplateName}}`, directly in the text nodes.
	 *
	 * @param DOMDocument $dom
	 *
	 * @return DOMText[] the text nodes that were changed
	 */
	private function processAsWikiMarkup( DOMDocument $dom ): array {
		$macroName = $this->getMacroName();
		$templateName = $this->getTemplateName();
		$regex = '/\{' . preg_quote( $macroName, '/' ) . '(?::([^}]*))?\}/';

		$touched = [];
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()[contains(., "{")]' ) as $textNode ) {
			$original = $textNode->nodeValue;

			$rewritten = preg_replace_callback(
				$regex,
				static function ( array $m ) use ( $templateName ) {
					$params = $m[1] ?? '';

					return $params === '' ? '{{' . $templateName . '}}' : '{{' . $templateName . '|' . $params . '}}';
				},
				$original
			);

			if ( $rewritten !== $original ) {
				$textNode->nodeValue = $rewritten . $this->getBrokenMacroCategory();
				$touched[] = $textNode;
			}
		}

		return $touched;
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return void
	 * @throws DOMException
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$templateName = $this->getTemplateName();
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				if ( $childNode instanceof DOMElement === false ) {
					continue;
				}
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === '' ) {
					continue;
				}

				if ( $paramName === "spaceKey" ) {
					$params[$paramName] = $this->extractSpaceKey( $childNode );
					continue;
				}

				$params[$paramName] = trim( $childNode->nodeValue );
			}
		}

		$paramsString = '';
		foreach ( $params as $key => $value ) {
			$paramsString .= "|$key=$value";
		}

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				"{{" . $templateName . $paramsString . "}}" . $this->getBrokenMacroCategory(),
				__METHOD__
			),
			$node
		);
	}

	/**
	 * Reads the space key from an <ac:parameter ac:name="spaceKey"> node in
	 * either storage representation:
	 *   <ac:parameter ...><ri:space ri:space-key="ABC"/></ac:parameter>
	 *   <ac:parameter ...>ABC</ac:parameter>
	 *
	 * @param DOMElement $childNode the ac:parameter element
	 *
	 * @return string|null the space key, or null if none is present
	 */
	private function extractSpaceKey( DOMElement $childNode ): ?string {
		$spaces = $childNode->getElementsByTagName( 'space' );
		if ( $spaces->length > 0 ) {
			$key = trim( $spaces->item( 0 )->getAttribute( 'ri:space-key' ) );

			return $key === '' ? null : $key;
		}

		$key = trim( $childNode->textContent );

		return $key === '' ? null : $key;
	}

	/**
	 * @return string
	 */
	private function getTemplateName(): string {
		return "Livesearch";
	}

	/**
	 * Replace when ERM49287 is implemented
	 *
	 * @return string
	 */
	private function getBrokenMacroCategoryHint(): string {
		return $this->getBrokenMacroCategory();
	}
}
