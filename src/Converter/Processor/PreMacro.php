<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class PreMacro extends HtmlTagMacroBase {

	public const MACRO_NAME = 'pre';

	public const TAG = 'pre';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct( private PlaceholderManager $placeholderManager ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function replacement( DOMElement $macro, array $params ): DOMNode {
		// Newer Pandoc versions drop <pre> and its whitespace, hence the placeholders.
		$attributes = '';
		foreach ( $this->mapParams( $params ) as $name => $value ) {
			$attributes .= ' ' . $name . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
		}

		$holder = $macro->ownerDocument->createElement( 'span' );
		$this->appendBody( $macro, $holder );
		$doc = $macro->ownerDocument;
		$fragment = $doc->createDocumentFragment();

		if ( !$holder->getElementsByTagName( '*' )->length ) {
			$text = htmlspecialchars( $holder->textContent, ENT_NOQUOTES );
			$fragment->appendChild( $doc->createTextNode(
				$this->placeholderManager->getPlaceholder( "<pre$attributes>$text</pre>" )
			) );
			return $fragment;
		}

		// Markup in the body is converted by Pandoc; only the tags are protected.
		$fragment->appendChild( $doc->createTextNode(
			$this->placeholderManager->getPlaceholder( "<pre$attributes>" )
		) );
		foreach ( iterator_to_array( $holder->childNodes ) as $node ) {
			$fragment->appendChild( $node );
		}
		$fragment->appendChild( $doc->createTextNode( $this->placeholderManager->getPlaceholder( '</pre>' ) ) );
		return $fragment;
	}
}
