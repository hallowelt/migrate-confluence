<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMXPath;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;

class LivesearchMacro extends StructuredMacroProcessorBase {

	private const TEMPLATE_NAME = 'TagSearch';

	/**
	 * Confluence livesearch param → TagSearch template param mapping.
	 * Unmapped Confluence params are silently dropped.
	 *
	 *   spaceKey    → namespace   (restrict results to one space)
	 *   labels      → category    (comma-separated labels)
	 *   placeholder → placeholder (grey prompt text inside the empty field)
	 */
	private const PARAM_MAP = [
		'spaceKey'    => 'namespace',
		'labels'      => 'category',
		'placeholder' => 'placeholder',
	];

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpace
	 */
	public function __construct( private IConverterDataWriter $writer, private int $currentSpace ) {
	}

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
	 * Rewrites `{livesearch:key=val|...}` and bare `{livesearch}` in text nodes,
	 * mapping supported Confluence params to TagSearch template params.
	 *
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processAsWikiMarkup( DOMDocument $dom ): void {
		$macroName = $this->getMacroName();
		$regex = '/\{' . preg_quote( $macroName, '/' ) . '(?::([^}]*))?\}/';

		$found = false;
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()[contains(., "{")]' ) as $textNode ) {
			$original = $textNode->nodeValue;

			$rewritten = preg_replace_callback(
				$regex,
				function ( array $m ) {
					$mapped = $this->mapParams(
						$this->parseWikiMarkupParams( $m[1] ?? '' )
					);
					return '{{' . self::TEMPLATE_NAME . $this->buildParamsString( $mapped ) . '}}';
				},
				$original
			);

			if ( $rewritten !== $original ) {
				$textNode->nodeValue = $rewritten;
				$found = true;
			}
		}

		if ( $found ) {
			$this->writer->registerDefaultPage( $this->currentSpace, self::TEMPLATE_NAME );
		}
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 * @throws DOMException
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			$paramName = $childNode->getAttribute( 'ac:name' );
			if ( $paramName === '' ) {
				continue;
			}
			$params[$paramName] = trim( $childNode->nodeValue );
		}

		$mapped = $this->mapParams( $params );

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				'{{' . self::TEMPLATE_NAME . $this->buildParamsString( $mapped ) . '}}',
				__METHOD__
			),
			$node
		);

		$this->writer->registerDefaultPage( $this->currentSpace, self::TEMPLATE_NAME );
	}

	/**
	 * Maps Confluence livesearch params to TagSearch template params,
	 * dropping any params not present in PARAM_MAP or with empty values.
	 *
	 * @param array $params
	 * @return array
	 */
	private function mapParams( array $params ): array {
		$mapped = [];
		foreach ( self::PARAM_MAP as $confluenceKey => $templateKey ) {
			if ( isset( $params[$confluenceKey] ) && $params[$confluenceKey] !== '' ) {
				$mapped[$templateKey] = $params[$confluenceKey];
			}
		}
		return $mapped;
	}

	/**
	 * Builds a `|key=value|...` string from a params array.
	 *
	 * @param array $params
	 * @return string
	 */
	private function buildParamsString( array $params ): string {
		$paramsString = '';
		foreach ( $params as $key => $value ) {
			$paramsString .= "|$key=$value";
		}
		return $paramsString;
	}

	/**
	 * Parses `key=val|key2=val2` into `['key' => 'val', 'key2' => 'val2']`.
	 *
	 * @param string $paramsStr
	 * @return array
	 */
	private function parseWikiMarkupParams( string $paramsStr ): array {
		if ( $paramsStr === '' ) {
			return [];
		}
		$result = [];
		foreach ( explode( '|', $paramsStr ) as $pair ) {
			if ( str_contains( $pair, '=' ) ) {
				[ $key, $value ] = explode( '=', $pair, 2 );
				$result[ trim( $key ) ] = $value;
			}
		}
		return $result;
	}
}
