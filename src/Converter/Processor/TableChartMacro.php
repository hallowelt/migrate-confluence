<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the "table-chart" macro (Table Filter and Charts for Confluence)
 * into the SimpleCharts tags `<linechart>`, `<barchart>` and `<piechart>`.
 *
 * Supported chart types: Line, Column (vertical bars) and Pie, with the
 * chart data being an inline table in the macro body and series being
 * columns ("dataorientation" = Vertical).
 *
 * Everything else (other chart types, data from `csv-table`/`spreadsheet-table`
 * attachments, horizontal orientation, non-numeric/negative values, ...) can not
 * be represented by SimpleCharts. Such macros are left untouched and flagged with
 * the broken-macro category, so the content is not silently lost.
 *
 * Parameters mapped: type, title, aggregation (series), column (label column),
 * colors/colorKeyMap (series colors), legend (showlegend), innerlabels (showvalues),
 * separator (decimal separator).
 * Parameters not mapped (SimpleCharts has no equivalent): tfc-height, align, ytitle,
 * is3d, log, xlog, grid, trendline, interpolation, line-settings, barColoringType.
 *
 * <ac:structured-macro ac:name="table-chart">
 *	<ac:parameter ac:name="type">Line</ac:parameter>
 *	<ac:parameter ac:name="title">Temperature</ac:parameter>
 *	<ac:parameter ac:name="column">Month</ac:parameter>
 *	<ac:parameter ac:name="aggregation">Min‚Max</ac:parameter>
 *	<ac:parameter ac:name="colors">#8eb021,#3572b0</ac:parameter>
 *	<ac:rich-text-body>
 *		<table><tbody>
 *			<tr><th>Month</th><th>Min</th><th>Max</th></tr>
 *			<tr><td>Jan</td><td>-1</td><td>5</td></tr>
 *		</tbody></table>
 *	</ac:rich-text-body>
 * </ac:structured-macro>
 */
class TableChartMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'table-chart';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'SimpleCharts' ];

	/** Confluence chart type (lowercase) => SimpleCharts tag */
	private const TYPE_MAP = [
		'line' => 'linechart',
		'column' => 'barchart',
		'pie' => 'piechart',
	];

	/** Separator used by the macro within list parameters (U+201A) */
	private const LIST_SEPARATOR = "\u{201A}";

	public function __construct( private PlaceholderManager $placeholderManager ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );
		$type = strtolower( trim( $params['type'] ?? '' ) );

		if ( !isset( self::TYPE_MAP[$type] ) ) {
			$this->markBroken( $node, $type === '' ? 'unknown' : $type );
			return;
		}

		$series = $this->getSeries( $node, $params, self::TYPE_MAP[$type] === 'piechart' );
		if ( $series === null ) {
			$this->markBroken( $node, $type );
			return;
		}

		$this->replaceWithChart( $node, self::TYPE_MAP[$type], $params, $series );
	}

	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement
				&& $childNode->nodeName === 'ac:parameter'
				&& $childNode->getAttribute( 'ac:name' ) !== ''
			) {
				$params[$childNode->getAttribute( 'ac:name' )] = $childNode->nodeValue;
			}
		}
		return $params;
	}

	/**
	 * @return array|null List of [ 'label' => string, 'rows' => [ [ label, value ], ... ] ]
	 *  or null if the data cannot be represented
	 */
	private function getSeries( DOMElement $node, array $params, bool $singleSeriesOnly ): ?array {
		if ( strtolower( trim( $params['dataorientation'] ?? 'vertical' ) ) !== 'vertical' ) {
			return null;
		}

		$grid = $this->getTableGrid( $node );
		if ( $grid === null || count( $grid ) < 2 ) {
			return null;
		}

		$header = array_shift( $grid );
		$labelColumn = $this->getLabelColumn( $params, $header );
		if ( $labelColumn === null ) {
			return null;
		}

		$seriesNames = $this->getSeriesNames( $params, $header, $labelColumn );
		if ( $seriesNames === [] || ( $singleSeriesOnly && count( $seriesNames ) > 1 ) ) {
			return null;
		}

		$decimalComma = stripos( $params['separator'] ?? '', 'comma' ) === 0;
		$series = [];
		foreach ( $seriesNames as $seriesName ) {
			$column = array_search( $seriesName, $header, true );
			if ( $column === false ) {
				return null;
			}
			$rows = [];
			foreach ( $grid as $cells ) {
				$label = $cells[$labelColumn] ?? '';
				$value = $this->normalizeNumber( $cells[$column] ?? '', $decimalComma );
				if ( $value === null || !$this->isValidLabel( $label ) ) {
					return null;
				}
				$rows[] = [ $label, $value ];
			}
			$series[] = [ 'label' => $seriesName, 'rows' => $rows ];
		}

		return $series;
	}

	/**
	 * @return array|null Rows of cell texts of the single inline table in the body
	 */
	private function getTableGrid( DOMElement $node ): ?array {
		$tables = null;
		foreach ( $node->childNodes as $child ) {
			if ( $child->nodeName === 'ac:rich-text-body' ) {
				$tables = $child->getElementsByTagName( 'table' );
			}
		}
		if ( $tables === null || $tables->length !== 1 ) {
			return null;
		}

		$grid = [];
		foreach ( $tables->item( 0 )->getElementsByTagName( 'tr' ) as $row ) {
			$cells = [];
			foreach ( $row->childNodes as $cell ) {
				if ( $cell instanceof DOMElement && in_array( $cell->nodeName, [ 'td', 'th' ] ) ) {
					$cells[] = trim( preg_replace( '/\s+/u', ' ', $cell->textContent ) );
				}
			}
			$grid[] = $cells;
		}
		return $grid;
	}

	private function getLabelColumn( array $params, array $header ): ?int {
		$labelName = trim( explode( self::LIST_SEPARATOR, $params['column'] ?? '' )[0] );
		if ( $labelName === '' ) {
			return 0;
		}
		$index = array_search( $labelName, $header, true );
		return $index === false ? null : $index;
	}

	/**
	 * @return string[]
	 */
	private function getSeriesNames( array $params, array $header, int $labelColumn ): array {
		$aggregation = trim( $params['aggregation'] ?? '' );
		if ( $aggregation !== '' ) {
			return array_map( 'trim', explode( self::LIST_SEPARATOR, $aggregation ) );
		}
		$names = $header;
		unset( $names[$labelColumn] );
		return array_values( $names );
	}

	/**
	 * @return string|null Normalized non-negative number or null if not representable
	 */
	private function normalizeNumber( string $raw, bool $decimalComma ): ?string {
		$number = str_replace( [ ' ', "\u{00A0}" ], '', $raw );
		$number = $decimalComma
			? str_replace( ',', '.', str_replace( '.', '', $number ) )
			: str_replace( ',', '', $number );
		// SimpleCharts skips negative values
		if ( !is_numeric( $number ) || (float)$number < 0 ) {
			return null;
		}
		return $number;
	}

	/**
	 * SimpleCharts treats lines starting with "#" as comments and "---key:" as series separators.
	 */
	private function isValidLabel( string $label ): bool {
		return $label !== '' && $label[0] !== '#' && !preg_match( '/^---\w+:/', $label );
	}

	private function replaceWithChart( DOMElement $node, string $tag, array $params, array $series ): void {
		$attributes = '';
		$title = trim( $params['title'] ?? '' );
		if ( $title !== '' ) {
			$attributes .= ' title="' . htmlspecialchars( $title, ENT_QUOTES ) . '"';
		}
		if ( stripos( $params['innerlabels'] ?? '', 'value' ) !== false ) {
			$attributes .= ' showvalues="true"';
		}
		if ( in_array( strtolower( trim( $params['legend'] ?? '' ) ), [ 'none', 'false', 'hidden' ], true ) ) {
			$attributes .= ' showlegend="false"';
		}

		$lines = [];
		$useSeparators = $tag !== 'piechart';
		$colors = $this->getColors( $params, $series );
		foreach ( $series as $index => $serie ) {
			if ( $useSeparators ) {
				$separator = '---';
				if ( $colors[$index] !== null ) {
					$separator .= 'color:' . $colors[$index] . ' ';
				}
				$lines[] = $separator . 'label:' . $serie['label'];
			}
			foreach ( $serie['rows'] as [ $label, $value ] ) {
				$lines[] = $this->csvField( $label ) . ',' . $value;
			}
		}

		$doc = $node->ownerDocument;
		$open = $this->placeholderManager->getPlaceholder( "<$tag$attributes>\n" );
		$close = $this->placeholderManager->getPlaceholder( "</$tag>\n" );
		$node->parentNode->insertBefore( $doc->createTextNode( "###BREAK###$open" ), $node );
		foreach ( $lines as $line ) {
			$node->parentNode->insertBefore( $doc->createTextNode( "$line###BREAK###" ), $node );
		}
		$node->parentNode->insertBefore( $doc->createTextNode( $close ), $node );
		$node->parentNode->removeChild( $node );
	}

	/**
	 * @return array<int, string|null> Color per series
	 */
	private function getColors( array $params, array $series ): array {
		$colorKeyMap = json_decode( urldecode( $params['colorKeyMap'] ?? '' ), true );
		$palette = array_map( 'trim', explode( ',', $params['colors'] ?? '' ) );

		$colors = [];
		foreach ( $series as $index => $serie ) {
			$color = is_array( $colorKeyMap ) ? ( $colorKeyMap[$serie['label']] ?? null ) : null;
			$color ??= $palette[$index] ?? null;
			$colors[$index] = is_string( $color ) && preg_match( '/^(#[0-9a-f]{3,8}|[a-z]+)$/i', $color )
				? $color : null;
		}
		return $colors;
	}

	private function csvField( string $value ): string {
		if ( preg_match( '/[",]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}
		return $value;
	}

	private function markBroken( DOMElement $node, string $type ): void {
		// The macro itself is kept, it is handled by "UnhandledMacroConverter" at the end of convert step.
		$category = $this->getCategoryBroken( "table-chart/$type" );
		$node->parentNode->insertBefore( $node->ownerDocument->createTextNode( $category ), $node );
	}
}
