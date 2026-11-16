<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * A `<p>` would lose all its attributes in Pandoc, hence a div with the paragraph role.
 */
class PMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'p';

	public const TAG = 'div';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	/**
	 * @inheritDoc
	 */
	protected function replacement( DOMElement $macro, array $params ): DOMNode {
		$element = parent::replacement( $macro, $params );
		$element->setAttribute( 'role', 'paragraph' );
		return $element;
	}
}
