<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

	/**
	 *
	 * <ac:macro ac:name="localtabgroup">
	 * <ac:rich-text-body>
	 * <ac:macro ac:name="localtab">
	 * <ac:parameter ac:name="title">...</ac:parameter>
	 * <ac:rich-text-body>...</ac:rich-text-body>
	 * </ac:macro>
	 * </ac:rich-text-body>
	 * </ac:macro>
	 */
class LocalTabMacro extends MacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'localtab';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$params = $this->getMacroParams( $node );

		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-localtab" );

		if ( isset( $params['title'] ) ) {
			$macroReplacement->appendChild(
				$node->ownerDocument->createElement( 'h1', $params['title'] )
			);
		}

		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
