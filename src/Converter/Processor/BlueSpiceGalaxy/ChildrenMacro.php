<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro as BaseChildrenMacro;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Lists subpages with the StandardTags pagelist tag directly in the page, without a wrapping template.
 */
class ChildrenMacro extends BaseChildrenMacro implements IUsesPlaceholder {

	public const REQUIRED_EXTENSIONS = [ 'StandardTags' ];

	private const HANDLED_PARAMS = [ 'page', 'sort', 'reverse', 'first', 'depth', 'all' ];

	/** Depth used for "all", i.e. all descendants. */
	private const ALL_DEPTH = 100;

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $spaceId
	 * @param string $wikiPageTitle
	 * @param DBConversionDataLookup $dataLookup
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		IConverterDataWriter $writer,
		int $spaceId,
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
		$sort = match ( $params['sort'] ?? '' ) {
			'creation' => 'created',
			'modified' => 'modified',
			default => 'title',
		};
		$depth = ( $params['all'] ?? '' ) === 'true' ? self::ALL_DEPTH : max( 1, (int)( $params['depth'] ?? 1 ) );

		$attributes = [
			'page' => $params['page'],
			'format' => 'list',
			'depth' => (string)$depth,
			'root' => 'false',
			'sort' => $sort,
			'order' => ( $params['reverse'] ?? '' ) === 'true' ? 'desc' : 'asc',
		];
		if ( (int)( $params['first'] ?? 0 ) > 0 ) {
			$attributes['max'] = (string)(int)$params['first'];
		}

		$wikiText = '<pagelist';
		foreach ( $attributes as $name => $value ) {
			$wikiText .= " $name=\"" . str_replace( '"', '&quot;', $value ) . '"';
		}
		$wikiText .= ' />';

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

	/**
	 * @inheritDoc
	 */
	protected function registerDefaultPages(): void {
	}
}
