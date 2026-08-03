<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMXPath;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class LivesearchMacro extends StructuredMacroProcessorBase {

	private const WIDGET_NAME = 'InlineSearch';

	/**
	 * Confluence livesearch params passed through to the InlineSearch widget.
	 * All other params are silently dropped — the widget always produces a working
	 * search box even without any params.
	 *
	 * Known Confluence livesearch params (for reference):
	 *   - placeholder  – grey prompt text inside the empty field            → passed through
	 *   - button       – label on the submit button (widget-specific)       → passed through
	 *   - spaceKey     – restrict results to one space (plain key or <ri:space>) → dropped
	 *   - size         – input width: "medium" (default) or "large"         → dropped
	 *   - type         – content type: page, blogpost/blog, comment, attachment → dropped
	 *   - additional   – extra info per result: none, space, excerpt/page excerpt → dropped
	 *   - labels       – restrict results to labelled content               → dropped
	 */
	private const SUPPORTED_PARAMS = [ 'placeholder', 'button' ];

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB ) {
		$this->workspaceDB = $workspaceDB;
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
	 * keeping only supported params.
	 *
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processAsWikiMarkup( DOMDocument $dom ): void {
		$macroName = $this->getMacroName();
		$widgetSyntax = $this->getWidgetSyntax();
		$regex = '/\{' . preg_quote( $macroName, '/' ) . '(?::([^}]*))?\}/';

		$found = false;
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()[contains(., "{")]' ) as $textNode ) {
			$original = $textNode->nodeValue;

			$rewritten = preg_replace_callback(
				$regex,
				function ( array $m ) use ( $widgetSyntax ) {
					$supported = $this->filterToSupportedParams(
						$this->parseWikiMarkupParams( $m[1] ?? '' )
					);
					return '{{' . $widgetSyntax . $this->buildParamsString( $supported ) . '}}';
				},
				$original
			);

			if ( $rewritten !== $original ) {
				$textNode->nodeValue = $rewritten;
				$found = true;
			}
		}

		if ( $found ) {
			$this->workspaceDB->addRequiredWidget( self::WIDGET_NAME );
		}
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 * @throws DOMException
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$widgetSyntax = $this->getWidgetSyntax();
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

		$supported = $this->filterToSupportedParams( $params );

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				'{{' . $widgetSyntax . $this->buildParamsString( $supported ) . '}}',
				__METHOD__
			),
			$node
		);

		$this->workspaceDB->addRequiredWidget( self::WIDGET_NAME );
	}

	/**
	 * Keeps only the params that the InlineSearch widget supports.
	 *
	 * @param array $params
	 * @return array
	 */
	private function filterToSupportedParams( array $params ): array {
		return array_intersect_key( $params, array_flip( self::SUPPORTED_PARAMS ) );
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

	/**
	 * Returns the parser function syntax for the widget (without outer braces).
	 *
	 * @return string e.g. "#widget:InlineSearch"
	 */
	private function getWidgetSyntax(): string {
		return '#widget:' . self::WIDGET_NAME;
	}
}
