<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroExcerptInclude extends StructuredMacroInclude {

	/**
	 * @var array
	 */
	private $parameters = [];

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'excerpt-include';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$parameterEls = $node->getElementsByTagName( 'parameter' );
		foreach ( $parameterEls as $parameterEl ) {
			$paramName = trim( $parameterEl->getAttribute( 'ac:name' ) ?? '' );
			if ( empty( $paramName ) ) {
				continue;
			}
			$paramValue = trim( $parameterEl->textContent ?? '' );
			$this->parameters[$paramName] = $paramValue;
		}
		parent::doProcessMacro( $node );
	}

	protected function makeTemplateCall(): string {
		$parameterList = '';
		foreach ( $this->parameters as $paramName => $paramValue ) {
			$parameterList .= "\n |" . $paramName . ' = ' . $paramValue . '###BREAK###';
		}
		return <<<WIKITEXT
{{ExcerptInclude###BREAK###
 |target = {$this->mediaWikiPageName}###BREAK###$parameterList
}}###BREAK###
WIKITEXT;
	}
}
