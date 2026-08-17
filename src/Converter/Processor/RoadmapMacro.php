<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DateTimeImmutable;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use RuntimeException;
use Throwable;

/**
 * Converts the Confluence "roadmap" macro into a standalone SVG rendering of the
 * roadmap diagram (month grid, lanes, bars, markers, bar links), embedded via the
 * `Roadmap` wiki template. All labels and links are drawn directly into the SVG
 * markup, there is no HTML overlay.
 *
 * @see https://confluence.atlassian.com/doc/roadmap-planner-macro-935385512.html
 */
class RoadmapMacro extends StructuredMacroProcessorBase {

	private const MONTHS = [
		'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
		'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
	];

	private const LEFT = 57;
	private const TOP = 70;
	private const COLUMN_WIDTH = 100;
	private const LANE_LABEL_WIDTH = 37;
	private const ROW_HEIGHT = 45;
	private const BAR_HEIGHT = 37;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param ConversionDataWriter $conversionDataWriter
	 * @param IConverterDataWriter $dataWriter
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private ConversionDataWriter $conversionDataWriter,
		private IConverterDataWriter $dataWriter,
		private int $currentSpaceId,
		private string $rawPageTitle
	) {
	}

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'roadmap';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );

		if ( !isset( $params['source'] ) || $params['source'] === '' ) {
			$node->parentNode->replaceChild(
				$this->createTextNode(
					$node->ownerDocument,
					sprintf( '{{Textbox|boxtype=warning|text=Missing parameter "source" in roadmap macro.}}%s', $this->getCategoryBrokenMacro( 'roadmap' ) ),
					__METHOD__ ),
				$node
			);
			return;
		}

		try {
			$source = $this->decodeSource( $params['source'] );
			$svg = $this->renderSvg( $source );
		} catch ( Throwable $e ) {
			$node->parentNode->replaceChild(
				$this->createTextNode(
					$node->ownerDocument,
					sprintf( '{{Textbox|boxtype=warning|text=%s}}%s', $e->getMessage(), $this->getCategoryBrokenMacro( 'roadmap' ) ),
					__METHOD__ ),
				$node
			);
			return;
		}

		$macroId = $node->getAttribute( 'ac:macro-id' );
		if ( $macroId === '' ) {
			$macroId = $node->getAttribute( 'ac:local-id' );
		}
		if ( $macroId === '' ) {
			$macroId = uniqid();
		}
		$filename = "Roadmap-$macroId.svg";

		$this->conversionDataWriter->replaceConfluenceFileContent( $filename, $svg );
		$this->dataWriter->addRoadmapSvg( $this->currentSpaceId, $this->rawPageTitle, $filename );

		$templateParams = [
			'filename' => $filename,
			'source' => $params['source'],
			'layout' => $node->getAttribute( 'data-layout' ),
		];
		unset( $params['source'] );
		foreach ( $params as $key => $value ) {
			if ( $value !== '' ) {
				$templateParams[$key] = $value;
			}
		}

		$paramsString = '';
		foreach ( $templateParams as $key => $value ) {
			$paramsString .= "|$key=$value\n";
		}

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, "{{Roadmap$paramsString}}", __METHOD__ ),
			$node
		);
	}

	/**
	 * @param DOMElement $macro
	 *
	 * @return array
	 */
	private function getMacroParams( DOMElement $macro ): array {
		$params = [];
		foreach ( $macro->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $childNode->nodeName === 'ac:parameter' ) {
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
	 * @param string $rawSource URL-encoded JSON, the "source" macro parameter's raw value
	 * @return array
	 */
	private function decodeSource( string $rawSource ): array {
		$data = json_decode( urldecode( $rawSource ), true );
		if ( !is_array( $data ) ) {
			throw new RuntimeException( 'roadmap macro "source" parameter is not valid JSON' );
		}

		return $data;
	}

	/**
	 * @param array $source decoded roadmap configuration (lanes, bars, markers, timeline)
	 * @return string standalone SVG markup
	 */
	private function renderSvg( array $source ): string {
		$timeline = $source['timeline'] ?? [];
		$start = $this->parseDate( $timeline['startDate'] ?? '1970-01-01 00:00:00' );
		$end = $this->parseDate( $timeline['endDate'] ?? '1970-01-01 00:00:00' );
		$firstMonth = $this->monthKey( $start );
		$months = max( 1, $this->monthKey( $end ) - $firstMonth + 1 );

		$lanes = $source['lanes'] ?? [];
		$rows = 1;
		foreach ( $lanes as $lane ) {
			foreach ( $lane['bars'] ?? [] as $bar ) {
				$rows = max( $rows, (int)( $bar['rowIndex'] ?? 0 ) + 1 );
			}
		}
		$laneHeight = $rows * self::ROW_HEIGHT + 8;
		$bottom = self::TOP + count( $lanes ) * $laneHeight;
		$width = self::LEFT + $months * self::COLUMN_WIDTH + 50;
		$height = $bottom + 91;

		$out = [];
		$out[] = sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
			$width, $height, $width, $height
		);
		$out[] = '<title>' . $this->esc( (string)( $source['title'] ?? 'Roadmap' ) ) . '</title>';
		$out[] = '<style>text{font-family:sans-serif}</style>';
		$out[] = '<rect width="100%" height="100%" fill="white"/>';

		$out[] = '<g font-size="13" fill="#707070" stroke="#707070" '
			. 'stroke-width=".3" stroke-dasharray="3.5,6.5">';
		for ( $i = 0; $i < $months; $i++ ) {
			$absolute = $firstMonth + $i;
			$year = intdiv( $absolute, 12 );
			$month = $absolute % 12;
			$x = self::LEFT + $i * self::COLUMN_WIDTH;
			$out[] = sprintf( '<line x1="%d" y1="%d" x2="%d" y2="%d"/>', $x, self::TOP, $x, $bottom );
			$out[] = sprintf( '<text x="%d" y="50" stroke="none">%s</text>', $x + 37, self::MONTHS[$month] );
			if ( $i === 0 || $month === 0 ) {
				$out[] = sprintf(
					'<text x="%d" y="35" font-weight="bold" stroke="none">%d</text>',
					$x + 33, $year
				);
			}
		}
		$out[] = '</g>';

		foreach ( $source['markers'] ?? [] as $marker ) {
			$markerDate = $this->parseDate( $marker['markerDate'] ?? '' );
			$x = $this->dateToX( $markerDate, $firstMonth );
			$title = $this->esc( (string)( $marker['title'] ?? '' ) );
			$out[] = sprintf(
				'<g fill="#d04437" stroke="#d04437" font-size="14">'
				. '<line x1="%.1F" y1="%d" x2="%.1F" y2="%d"/>'
				. '<text x="%.1F" y="%d" stroke="none">%s</text></g>',
				$x, self::TOP, $x, $bottom + 10,
				$x - strlen( $title ) * 4, $bottom + 28, $title
			);
		}

		foreach ( $lanes as $laneIndex => $lane ) {
			$laneY = self::TOP + $laneIndex * $laneHeight;
			$colors = $lane['color'] ?? [];
			$laneColor = $colors['lane'] ?? '#f6c342';
			$textColor = $colors['text'] ?? '#000000';
			$barColor = $colors['bar'] ?? $laneColor;

			$out[] = sprintf(
				'<rect x="10" y="%d" width="%d" height="%d" fill="%s" stroke="#d1d1d1"/>',
				$laneY, self::LANE_LABEL_WIDTH, $laneHeight, $this->esc( $laneColor )
			);
			$center = $laneY + $laneHeight / 2;
			$labelX = 10 + self::LANE_LABEL_WIDTH / 2;
			$out[] = sprintf(
				'<text x="%.1F" y="%.1F" fill="%s" font-weight="bold" text-anchor="middle" '
				. 'dominant-baseline="central" transform="rotate(-90 %.1F %.1F)"%s>%s</text>',
				$labelX, $center, $this->esc( $textColor ), $labelX, $center,
				$this->whiteTextOutline( $textColor, $laneColor ),
				$this->esc( (string)( $lane['title'] ?? '' ) )
			);

			foreach ( $lane['bars'] ?? [] as $bar ) {
				$barDate = $this->parseDate( $bar['startDate'] ?? '' );
				$x = $this->dateToX( $barDate, $firstMonth );
				$y = $laneY + (int)( $bar['rowIndex'] ?? 0 ) * self::ROW_HEIGHT + 8;
				$barWidth = max( 2.0, (float)( $bar['duration'] ?? 1 ) * self::COLUMN_WIDTH - 2 );

				$out[] = sprintf(
					'<rect x="%.1F" y="%d" width="%.1F" height="%d" rx="2" fill="%s"/>',
					$x, $y, $barWidth, self::BAR_HEIGHT, $this->esc( $barColor )
				);

				$title = $this->esc( (string)( $bar['title'] ?? '' ) );
				$text = sprintf(
					'<text x="%.1F" y="%d" fill="%s" font-weight="bold"%s>%s</text>',
					$x + 8, $y + 24, $this->esc( $textColor ),
					$this->whiteTextOutline( $textColor, $barColor ), $title
				);

				$page = $bar['pageLink'] ?? [];
				$href = $this->pageHref( $page );
				if ( $href !== '' ) {
					$text = sprintf(
						'<a href="%s" data-page-id="%s">%s</a>',
						$this->esc( $href ), $this->esc( (string)( $page['id'] ?? '' ) ), $text
					);
				}
				$out[] = $text;
			}
		}

		$out[] = sprintf(
			'<g stroke="#d1d1d1"><line x1="20" y1="%d" x2="%d" y2="%d"/>', self::TOP, $width - 50, self::TOP
		);
		for ( $i = 1; $i <= count( $lanes ); $i++ ) {
			$y = self::TOP + $i * $laneHeight;
			$out[] = sprintf( '<line x1="20" y1="%d" x2="%d" y2="%d"/>', $y, $width - 50, $y );
		}
		$out[] = '</g>';
		$out[] = '</svg>';

		return implode( "\n", $out );
	}

	/**
	 * White text on a colored background reads as thinner than its font-weight
	 * suggests; outlining it in the background color restores the visual weight
	 * without the stroke bleeding past the fill (paint-order puts stroke first).
	 *
	 * @param string $textColor
	 * @param string $fillColor
	 * @return string
	 */
	private function whiteTextOutline( string $textColor, string $fillColor ): string {
		if ( strtolower( $textColor ) !== '#ffffff' ) {
			return '';
		}

		return sprintf( ' stroke="%s" stroke-width="3" paint-order="stroke"', $this->esc( $fillColor ) );
	}

	/**
	 * Resolves a bar's "pageLink" into an href for the SVG's <a>.
	 *
	 * MediaWiki's SVG sanitizer strips hrefs that aren't absolute http(s) URLs, so
	 * anything else (an internal wiki title, a relative Confluence path, ...) must be
	 * prefixed with "#internal#" (stripped again in a later step).
	 *
	 * @param array $page bar's "pageLink" value
	 * @return string
	 */
	private function pageHref( array $page ): string {
		if ( !empty( $page['url'] ) ) {
			return $this->internalizeHref( (string)$page['url'] );
		}
		if ( !empty( $page['id'] ) ) {
			$wikiTitle = $this->dataLookup->getWikiPageTitleFromPageId( (int)$page['id'] );
			if ( $wikiTitle !== null ) {
				return $this->internalizeHref( $wikiTitle );
			}

			// Page was not migrated (or the ID is unknown); fall back to the original
			// Confluence URL so the link still points somewhere.
			return $this->internalizeHref( '/pages/viewpage.action?pageId=' . $page['id'] );
		}

		return '';
	}

	/**
	 * prefix non-absolute URLs
	 *
	 * This is a workaround for MediaWiki's SVG validator, which complains about relative URLs.
	 * It is expected that a later view implementation will strip the "#internal#" prefix and
	 * convert the URL to a proper internal link while rendering the SVG functionally.
	 *
	 * @param string $href
	 * @return string $href unchanged if it is an absolute http(s) URL, otherwise prefixed
	 *   with "#internal#"
	 */
	private function internalizeHref( string $href ): string {
		if ( preg_match( '/^https?:\/\//i', $href ) === 1 ) {
			return $href;
		}

		return '#internal#' . $href;
	}

	/**
	 * @param string $value
	 * @return DateTimeImmutable
	 */
	private function parseDate( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', substr( $value, 0, 19 ) );
		if ( $date === false ) {
			throw new RuntimeException( 'invalid roadmap date: ' . $value );
		}

		return $date;
	}

	/**
	 * @param DateTimeImmutable $date
	 * @return int
	 */
	private function monthKey( DateTimeImmutable $date ): int {
		return ( (int)$date->format( 'Y' ) ) * 12 + ( (int)$date->format( 'n' ) ) - 1;
	}

	/**
	 * @param DateTimeImmutable $date
	 * @param int $firstMonth
	 * @return float
	 */
	private function dateToX( DateTimeImmutable $date, int $firstMonth ): float {
		$fraction = ( (int)$date->format( 'j' ) - 1 ) / (int)$date->format( 't' );

		return self::LEFT + ( $this->monthKey( $date ) - $firstMonth + $fraction ) * self::COLUMN_WIDTH;
	}

	/**
	 * @param string $value
	 * @return string
	 */
	private function esc( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}
}
