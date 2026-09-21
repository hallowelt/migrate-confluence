<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Convert into <status>
 *
 * <ac:structured-macro ac:name="chart" ac:schema-version="1">
 *	<ac:parameter ac:name="type">pie</ac:parameter>
 *	<ac:parameter ac:name="title">Chart title text</ac:parameter>
 *	<ac:rich-text-body>
 *		<table>
 *			<tbody>
 *				<tr>
 *					<th>Name</th>
 *					<th>Value</th>
 *				</tr>
 *				<tr>
 *					<td>A</td>
 *					<td>5</td>
 *				</tr>
 *				<tr>
 *					<td>B</td>
 *					<td>5</td>
 *				</tr>
 *			</tbody>
 *		</table>
 *	</ac:rich-text-body>
 * </ac:structured-macro>
 */
class ChartMacro extends StructuredMacroProcessorBase {

	// Pie Chart: Showing parts of a whole (proportions)
	private const PIE = 'pie';

	// Bar Chart: Comparing discrete category quantities
	private const BAR = 'bar';

	// Line Graph: Tracking metrics and changes over time
	private const LINE = 'line';

	// Area Chart: Tracking changes while highlighting volume totals
	private const AREA = 'area';

	// Scatter Plot: Exploring relationships between two numerical variables
	private const SCATTER = 'scatter';

	// Gantt Chart: Project timelines, tasks, and schedules
	private const GANTT = 'gantt';

	/**
	 * Allowed chart types
	 *
	 * @var array
	 */
	private $allowedChartTypes = [
		self::PIE,
		self::BAR,
		self::LINE,
		self::AREA,
		self::SCATTER,
		self::GANTT,
	];

	/**
	 * @var ConversionHelper|null
	 */
	private ?ConversionHelper $helper = null;

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct( private PlaceholderManager $placeholderManager ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'chart';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );

		if ( !isset( $params['type'] )
			|| !in_array( $params['type'], $this->allowedChartTypes, true )
		) {
			// If the chart type is not set, we cannot process this macro.
			// It will be handled by "UnhandledMacroConverter" at the end of convert step.
			return;
		}

		$this->helper = new ConversionHelper();

		$this->prependWithTitle( $node, $params );

		switch ( $params['type'] ) {
			case self::PIE:
				$this->handlePieChart( $node, $params );
				break;
			case self::BAR:
				$this->handleBarChart( $node, $params );
				break;
			case self::LINE:
				$this->handleLineChart( $node, $params );
				break;
			case self::AREA:
				$this->handleAreaChart( $node, $params );
				break;
			case self::SCATTER:
				$this->handleScatterPlot( $node, $params );
				break;
			case self::GANTT:
				$this->handleGanttChart( $node, $params );
				break;
			default:
				// Invalid chart type, cannot process this macro.
				// It will be handled by "UnhandledMacroConverter" at the end of convert step.
				return;
		}
	}

	/**
	 * @param DOMElement $macro
	 *
	 * @return array
	 */
	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				if ( $childNode instanceof DOMElement === false ) {
					continue;
				}
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === '' ) {
					continue;
				}
				$params[$paramName] = $childNode->nodeValue;
			}
		}
		return $params;
	}

	/**
	 * @param DOMElement $macro
	 * @return DOMElement|null
	 */
	private function macroBody( DOMElement $macro ): ?DOMElement {
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				return $childNode;
			}
		}
		return null;
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handlePieChart( DOMElement $node, array $params ): void {
		$this->handleSimpleChart( 'piechart', $node, $params );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleBarChart( DOMElement $node, array $params ): void {
		$this->handleSimpleChart( 'barchart', $node, $params );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleLineChart( DOMElement $node, array $params ): void {
		$this->handleSimpleChart( 'linechart', $node, $params );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleAreaChart( DOMElement $node, array $params ): void {
		// There is no equivalent in BlueSpiceGalaxy to handle this chart.
		// It will be handled by "UnhandledMacroConverter" at the end of convert step.
		// We simply mark it as unsupported.
		$this->handleUnsupportedChart( 'area', $node );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleScatterPlot( DOMElement $node, array $params ): void {
		// There is no equivalent in BlueSpiceGalaxy to handle this chart.
		// It will be handled by "UnhandledMacroConverter" at the end of convert step.
		// We simply mark it as unsupported.
		$this->handleUnsupportedChart( 'scatter', $node );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleGanttChart( DOMElement $node, array $params ): void {
		// There is no equivalent in BlueSpiceGalaxy to handle this chart.
		// It will be handled by "UnhandledMacroConverter" at the end of convert step.
		// We simply mark it as unsupported.
		$this->handleUnsupportedChart( 'gantt', $node );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function prependWithTitle( DOMElement $node, array $params ): void {
		if ( isset( $params['title'] ) && !empty( $params['title'] ) ) {
			$label = $node->ownerDocument->createElement( 'strong' );
			$label->textContent = $params['title'];
			$node->parentNode->insertBefore( $label, $node );
		}
	}

	/**
	 * @param string $type
	 * @param DOMElement $node
	 * @param array $params
	 * @return void
	 */
	private function handleSimpleChart( string $type, DOMElement $node, array $params ): void {
		$data = $this->getChartData( $node, $params );

		$replacement = $node->ownerDocument->createElement( $type );
		$replacement->setAttribute( 'showvalues', 'true' );
		$placeholderOpen = $this->placeholderManager->getPlaceholder(
				"<$type showvalues=\"true\">\n"
		);
		$placeholderClose = $this->placeholderManager->getPlaceholder(
				"</$type>\n"
		);
		$item = $node->ownerDocument->createTextNode( "###BREAK###$placeholderOpen" );
		$node->parentNode->insertBefore( $item, $node );
		foreach ( $data as $key => $value ) {
			$item = $node->ownerDocument->createTextNode( "$key, $value###BREAK###" );
			$node->parentNode->insertBefore( $item, $node );
		}
		$item = $node->ownerDocument->createTextNode( "$placeholderClose" );
		$node->parentNode->insertBefore( $item, $node );
		$node->parentNode->replaceChild( $replacement, $node );
	}

	/**
	 * @param DOMElement $node
	 * @param array $params
	 * @return array
	 */
	private function getChartData( DOMElement $node, array $params ): array {
		$body = $this->macroBody( $node );
		if ( !$body ) {
			return [];
		}
		$tables = $body->getElementsByTagName( 'table' );
		if ( $tables->count() < 1 ) {
			return [];
		}
		$table = $tables->item( 0 );
		$rows = $table->getElementsByTagName( 'tr' );
		if ( $rows->count() < 1 ) {
			return [];
		}

		$data = [];
		foreach ( $rows as $rowIndex => $row ) {
			$headerCells = $row->getElementsByTagName( 'th' );
			if ( $rowIndex === 0 && $headerCells->count() > 0 ) {
				// Header cells are not requiered.
				// They just contain "Name" and "Value" as text
				continue;
			}

			$dataCells = $row->getElementsByTagName( 'td' );
			if ( $dataCells->count() < 2 ) {
				continue;
			}
			$key = $dataCells->item( 0 )->textContent;
			$value = $dataCells->item( 1 )->textContent;
			$data[$key] = $value;
		}
		return $data;
	}

	/**
	 * @param string $type
	 * @param DOMElement $node
	 * @return void
	 */
	private function handleUnsupportedChart( string $type, DOMElement $node ): void {
		$category = '';
		if ( $this->helper instanceof ConversionHelper ) {
			$category = $this->helper->getCategoryBroken( "chart/$type" );
			$replacement = $node->ownerDocument->createTextNode( $category );
			$node->parentNode->insertBefore( $replacement, $node );
		}
	}
}
