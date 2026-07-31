<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMException;
use DOMText;
use DOMXPath;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class LivesearchMacro extends StructuredMacroProcessorBase {

	private const WIDGET_NAME = 'InlineSearch';

	/**
	 * Params fully supported by the InlineSearch widget.
	 * All other params indicate a broken/degraded conversion and trigger the broken-macro category.
	 *
	 * Known Confluence livesearch params (for reference):
	 *   - placeholder  – grey prompt text inside the empty field            → SUPPORTED
	 *   - button       – label on the submit button (widget-specific)       → SUPPORTED
	 *   - spaceKey     – restrict results to one space (plain key or <ri:space>) → unsupported
	 *   - size         – input width: "medium" (default) or "large"         → unsupported
	 *   - type         – content type: page, blogpost/blog, comment, attachment → unsupported
	 *   - additional   – extra info per result: none, space, excerpt/page excerpt → unsupported
	 *   - labels       – restrict results to labelled content               → unsupported
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
	 * Rewrites `{macroName:a=1|b=2}` -> `{{#widget:InlineSearch|a=1|b=2}}` and the
	 * bare `{macroName}` -> `{{#widget:InlineSearch}}`, directly in the text nodes.
	 *
	 * @param DOMDocument $dom
	 *
	 * @return DOMText[] the text nodes that were changed
	 */
	private function processAsWikiMarkup( DOMDocument $dom ): array {
		$macroName = $this->getMacroName();
		$widgetSyntax = $this->getWidgetSyntax();
		$regex = '/\{' . preg_quote( $macroName, '/' ) . '(?::([^}]*))?\}/';

		$touched = [];
		$xpath = new DOMXPath( $dom );
		foreach ( $xpath->query( '//text()[contains(., "{")]' ) as $textNode ) {
			$original = $textNode->nodeValue;

			$rewritten = preg_replace_callback(
				$regex,
				static function ( array $m ) use ( $widgetSyntax ) {
					$params = $m[1] ?? '';

					return $params === ''
						? '{{' . $widgetSyntax . '}}'
						: '{{' . $widgetSyntax . '|' . $params . '}}';
				},
				$original
			);

			if ( $rewritten !== $original ) {
				$paramsStr = preg_match( $regex, $original, $m ) ? ( $m[1] ?? '' ) : '';
				$broken = $this->hasUnsupportedParams( $this->parseWikiMarkupParams( $paramsStr ) );
				$textNode->nodeValue = $rewritten . ( $broken ? $this->getBrokenMacroCategory() : '' );
				$touched[] = $textNode;
			}
		}

		if ( !empty( $touched ) ) {
			$this->workspaceDB->addRequiredWidget( self::WIDGET_NAME );
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
		$widgetSyntax = $this->getWidgetSyntax();
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
				"{{" . $widgetSyntax . $paramsString . "}}"
					. ( $this->hasUnsupportedParams( $params ) ? $this->getBrokenMacroCategory() : '' ),
				__METHOD__
			),
			$node
		);

		$this->workspaceDB->addRequiredWidget( self::WIDGET_NAME );
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
	 * Returns true if any key in $params is not in SUPPORTED_PARAMS.
	 *
	 * @param array $params keys are param names
	 * @return bool
	 */
	private function hasUnsupportedParams( array $params ): bool {
		foreach ( array_keys( $params ) as $key ) {
			if ( !in_array( $key, self::SUPPORTED_PARAMS, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Parses a wiki-markup param string like "spaceKey=ABC|size=medium|placeholder=Foo"
	 * into an associative array.
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
				[ $key, ] = explode( '=', $pair, 2 );
				$result[ trim( $key ) ] = true;
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
