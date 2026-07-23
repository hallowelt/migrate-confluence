<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMException;
use DOMNode;
use Exception;

class ExcerptIncludeMacro extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'excerpt-include';
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'excerpt-include' );
		if ( !$macroReplacement ) {
			throw new Exception( 'Could not create excerpt-include element' );
		}

		$targetPage = $this->findPageParameter( $node );
		if ( $targetPage ) {
			$macroReplacement->setAttribute( 'page', $targetPage );
		}

		$options = $this->findOptionsParameters( $node );
		$macroReplacement->setAttribute( 'showpanel', !!$options['nopanel'] ? "true" : "false" );

		if ( !empty( $options['name'] ) ) {
			$macroReplacement->setAttribute( 'excerpt', $options['name'] );
		}

		if ( !$targetPage ) {
			$macroReplacement = $this->setMacroAsBroken( $macroReplacement, $node );
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return array
	 */
	private function findOptionsParameters( DOMElement $node ): array {
		$options = [
			'nopanel' => true,
			'name' => null
		];

		foreach ( $node->getElementsByTagName( 'parameter' ) as $parameter ) {
			$parameterName = $parameter->getAttribute( 'ac:name' );
			$paramValue = $parameter->textContent;

			if ( ( $parameterName !== "nopanel" && $parameterName !== "name" ) || empty ( $paramValue ) ) {
				continue;
			}

			$options[$parameterName] = trim( $paramValue );
		}

		return $options;
	}

	/**
	 * @param DOMElement $node
	 *
	 * Target page is in default parameter.
	 * Either ac:name="" or ac:default-parameter
	 *
	 * @return string|null
	 */
	private function findPageParameter( DOMElement $node ): ?string {
		$defaultParameter = $node->getElementsByTagName( 'default-parameter' )->item( 0 );
		if ( $defaultParameter ) {
			return $this->findPageValue( $defaultParameter );
		}

		foreach ( $node->getElementsByTagName( 'parameter' ) as $parameter ) {
			$nameParameter = $parameter->getAttribute( 'ac:name' );
			if ( $nameParameter === "" ) {
				return $this->findPageValue( $parameter );
			}
		}

		return null;
	}

	/**
	 * Resolve the referenced page title from an excerpt-include default parameter.
	 *
	 * The parameter either wraps an <ac:link><ri:page ri:content-title="…"/></ac:link>
	 * or holds the page title as plain text.
	 *
	 * @param DOMElement $pageParameter
	 *
	 * @return string|null The page title, or null if it can't be determined.
	 */
	private function findPageValue( DOMElement $pageParameter ): ?string {
		$page = null;
		$pageLink = $pageParameter->getElementsByTagName( 'link' )->item( 0 );
		if ( $pageLink instanceof DOMElement ) {
			$pageEl = $pageLink->getElementsByTagName( 'page' )->item( 0 );
			if ( $pageEl instanceof DOMElement ) {
				$page = $pageEl->getAttribute( 'ri:content-title' );
			}
		}

		if ( !$page ) {
			$page = $pageParameter->textContent;
		}

		$page = trim( $page );
		return $page === '' ? null : $page;
	}

	/**
	 * @param DOMElement $macroReplacement
	 * @param DOMElement $node
	 *
	 * @return DOMElement
	 * @throws DOMException
	 */
	private function setMacroAsBroken( DOMElement $macroReplacement, DOMElement $node ): DOMNode {
		$wikiText = $macroReplacement->textContent;
		$wikiText .= $this->getBrokenMacroCategory();

		return $this->createTextNode( $node->ownerDocument, $wikiText, __METHOD__ );
	}
}
