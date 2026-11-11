<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro as BaseChildrenMacro;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Lists subpages with SubPageList or DynamicPageList4 directly in the page, without a wrapping template.
 */
class ChildrenMacro extends BaseChildrenMacro implements IUsesPlaceholder {

	public const REQUIRED_EXTENSIONS = [ 'DynamicPageList4', 'ParserFunctions', 'SubPageList' ];

	private const HANDLED_PARAMS = [ 'page', 'sort', 'reverse', 'first' ];

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $spaceId
	 * @param string $wikiPageTitle
	 * @param DBConversionDataLookup $dataLookup
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		private readonly IConverterDataWriter $writer,
		private readonly int $spaceId,
		string $wikiPageTitle,
		DBConversionDataLookup $dataLookup,
		private readonly PlaceholderManager $placeholderManager
	) {
		parent::__construct( $writer, $spaceId, $wikiPageTitle, $dataLookup );
	}

	/**
	 * @inheritDoc
	 */
	protected function buildWikiText( array $params ): string {
		$page = $params['page'];
		$sort = $params['sort'] ?? '';
		$reverse = ( $params['reverse'] ?? '' ) === 'true';
		$limit = (int)( $params['first'] ?? 0 ) > 0 ? (int)$params['first'] : 500;

		if ( $sort === 'creation' || $sort === 'modified' ) {
			$orderMethod = $sort === 'creation' ? 'firstedit' : 'lastedit';
			$wikiText = $this->buildDplQuery( $page, $orderMethod, $reverse, $limit );
		} else {
			$order = $reverse ? 'desc' : 'asc';
			$wikiText = "{{#subpages:page=$page|pathstyle=full|template=SubpageListRow|limit=$limit|sort=$order}}";
		}

		$unhandled = [];
		foreach ( $params as $name => $value ) {
			if ( !in_array( $name, self::HANDLED_PARAMS, true ) ) {
				$unhandled[] = "$name=$value";
			}
		}
		if ( $unhandled ) {
			$wikiText .= '<!-- children macro parameters not migrated: '
				. str_replace( '--', '- -', implode( ', ', $unhandled ) ) . ' -->';
		}

		return $this->placeholderManager->getPlaceholder( $wikiText );
	}

	private function buildDplQuery( string $page, string $orderMethod, bool $reverse, int $limit ): string {
		$order = $reverse ? 'descending' : 'ascending';

		return "{{#dpl:\n"
			. "|namespace={{#if:{{NAMESPACE:$page}}|{{NAMESPACE:$page}}|Main}}\n"
			. "|titlematch={{#replace:{{PAGENAME:$page}}| |_}}/%\n"
			. "|ordermethod=$orderMethod\n"
			. "|order=$order\n"
			. "|count=$limit\n"
			. "|format=,\\n*²{SubpageListRow¦%PAGE%}²,,\n"
			. "}}";
	}

	/**
	 * @inheritDoc
	 */
	protected function registerDefaultPages(): void {
		$this->writer->registerDefaultPage( $this->spaceId, 'SubpageListRow' );
	}
}
