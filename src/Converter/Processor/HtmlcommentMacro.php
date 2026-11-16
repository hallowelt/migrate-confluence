<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class HtmlcommentMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'htmlcomment';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct( private PlaceholderManager $placeholderManager ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function replacement( DOMElement $macro, array $params ): DOMNode {
		$holder = $macro->ownerDocument->createElement( 'span' );
		$this->appendBody( $macro, $holder );
		// "--" would end the comment early; markup inside the body is reduced to its text.
		// Pandoc drops HTML comments, hence the placeholder.
		$text = str_replace( '--', '- -', $holder->textContent );
		return $macro->ownerDocument->createTextNode(
			$this->placeholderManager->getPlaceholder( "<!--$text-->" )
		);
	}
}
