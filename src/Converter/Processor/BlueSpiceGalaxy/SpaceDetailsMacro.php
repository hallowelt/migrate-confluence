<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\SpaceDetailsMacro as BaseSpaceDetailsMacro;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Renders the details of the wiki with the StandardTags wikistats tag, without a wrapping template.
 */
class SpaceDetailsMacro extends BaseSpaceDetailsMacro implements IUsesPlaceholder {

	public const REQUIRED_EXTENSIONS = [ 'StandardTags' ];

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		IConverterDataWriter $writer,
		int $currentSpaceId,
		private readonly PlaceholderManager $placeholderManager
	) {
		parent::__construct( $writer, $currentSpaceId );
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$wikiText = '<wikistats />';
		$width = $this->readParams( $node )['width'];
		if ( $width !== '100%' ) {
			$wikiText .= '<!-- space-details macro parameters not migrated: width='
				. str_replace( '--', '- -', $width ) . ' -->';
		}

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( $wikiText ),
				__METHOD__
			),
			$node
		);
	}
}
