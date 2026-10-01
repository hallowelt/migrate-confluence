<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

/**
 * Handles Confluence's "inc-drawio" macro, which embeds a DrawIO diagram that is attached
 * to a *different* page (identified by the "pageId" parameter) instead of the current page.
 */
class IncDrawioMacro extends DrawioMacro {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'inc-drawio';
	}

	/**
	 * @inheritDoc
	 */
	protected function makeParamsString( array $params, ?int $spaceId = null, ?string $rawPageTitle = null ): string {
		if ( !isset( $params['diagramName'] ) ) {
			return '';
		}

		if ( isset( $params['pageId'] ) && $params['pageId'] !== '' ) {
			[ $spaceId, $rawPageTitle ] = $this->resolveSourcePage( (int)$params['pageId'] );
		}

		// These parameters are only needed to resolve the source page/diagram above,
		// the Drawio template itself has no use for them.
		unset( $params['pageId'], $params['includedDiagram'] );

		return parent::makeParamsString( $params, $spaceId, $rawPageTitle );
	}

	/**
	 * Resolve the space and confluence page title the embedded diagram actually lives on.
	 * Falls back to the current space/page if the referenced page cannot be found.
	 *
	 * @param int $pageId
	 * @return array{0: int, 1: string} [ spaceId, rawPageTitle ]
	 */
	private function resolveSourcePage( int $pageId ): array {
		$resolvedSpaceId = $this->dataLookup->getSpaceIdForPageId( $pageId );
		$resolvedPageTitle = $this->dataLookup->getConfluencePageTitleFromPageId( $pageId );

		if ( $resolvedSpaceId === null || $resolvedPageTitle === null ) {
			return [ $this->currentSpaceId, $this->rawPageTitle ];
		}

		return [ $resolvedSpaceId, $resolvedPageTitle ];
	}
}
