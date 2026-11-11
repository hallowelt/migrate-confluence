<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\SpaceDetailsMacro as BaseSpaceDetailsMacro;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Renders the space details table with DynamicPageList4 directly in the page, without a wrapping template.
 */
class SpaceDetailsMacro extends BaseSpaceDetailsMacro implements IUsesPlaceholder {

	public const REQUIRED_EXTENSIONS = [ 'DynamicPageList4', 'ParserFunctions' ];

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
		$width = $this->readParams( $node )['width'];

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( $this->buildTable( $width ) ),
				__METHOD__
			),
			$node
		);
	}

	private function buildTable( string $width ): string {
		// The leading newline keeps the table start at the beginning of a line, as MediaWiki does for templates.
		return "\n{| class=\"wikitable\" style=\"width:$width\"\n"
			. "|-\n"
			. "| Name || {{NAMESPACE}}\n"
			. "|-\n"
			. "| Pages in namespace || " . $this->buildQuery( [ 'resultsheader=%TOTALPAGES%', 'format=,,,' ] ) . "\n"
			. "|-\n"
			. "| Last page edit || " . $this->buildQuery( $this->latestEdit( [ 'format=,[[%PAGE%]],,' ] ) ) . "\n"
			. "|-\n"
			. "| Last modification date || "
			. $this->buildQuery( $this->latestEdit( [ 'addeditdate=true', 'format=,%DATE%,,' ] ) ) . "\n"
			. "|-\n"
			. "| Last modifier || "
			. $this->buildQuery(
				$this->latestEdit( [ 'addlasteditor=true', 'format=,[[User:%USER%¦%USER%]],,' ] )
			) . "\n"
			. "|}";
	}

	/**
	 * @param string[] $options
	 * @return string[]
	 */
	private function latestEdit( array $options ): array {
		return array_merge( [ 'ordermethod=lastedit', 'order=descending' ], $options );
	}

	/**
	 * @param string[] $options
	 * @return string
	 */
	private function buildQuery( array $options ): string {
		$options = array_merge( [ 'namespace={{#if:{{NAMESPACE}}|{{NAMESPACE}}|Main}}', 'count=1' ], $options );

		return "{{#dpl:\n|" . implode( "\n|", $options ) . "\n}}";
	}
}
