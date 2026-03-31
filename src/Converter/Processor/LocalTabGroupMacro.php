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
class LocalTabGroupMacro extends MacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'localtabgroup';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-localtabgroup" );

		// Append the "<headertabs />" tag
		$macroReplacement->appendChild(
			$node->ownerDocument->createTextNode( '<headertabs />' )
		);

		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
