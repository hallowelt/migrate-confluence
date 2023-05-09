<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MigrateConfluence\Utility\CQLParser;

class StructuredMacroContenByLabel extends StructuredMacroProcessorBase {

	/**
	 * @var string
	 */
	private $currentPageTitle = '';

	/**
	 * @param string $currentPageTitle
	 */
	public function __construct( string $currentPageTitle ) {
		$this->currentPageTitle = $currentPageTitle;
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function getMacroName(): string {
		return 'contentbylabel';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$paramNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramNodes[] = $childNode;
			}
		}

		$params = [];
		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}
			
			$name = $paramNode->getAttribute( 'ac:name' );
			if ( $name === 'page' ) {
				$params[$name] = $this->currentPageTitle;
				continue;
			}

			$params[$name] = $paramNode->nodeValue;
		}
		
		if ( isset( $params['cql'] ) ) {
			$params['conditions'] = $this->getConditionsForCQL( $params['cql'] );
		}

		$templateParams = '';
		foreach ( $params as $key => $value ) {
			$templateParams .= "|$key=$value\n";
		}
		if ( empty( $params ) ) {
			$text = $node->ownerDocument->createTextNode( "[[Category:Broken_macro/" . $this->getMacroName() . "]]" );
		} else {
			// https://github.com/JeroenDeDauw/SubPageList/blob/master/doc/USAGE.md
			$text = $node->ownerDocument->createTextNode( "{{ContentByLabel\n$templateParams}}" );
		}

		$node->parentNode->replaceChild( $text, $node );
	}

	/**
	 * @param string $cql
	 * @return string
	 */
	private function getConditionsForCQL( string $cql ): string {
		$cqlParser = new CQLParser();
		$conditions = $cqlParser->parse( $cql );

		return $conditions;
	}
}
