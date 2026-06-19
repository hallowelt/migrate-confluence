<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

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
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-localtabgroup" );

		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );
		// Append the "<headertabs />" tag
		$macroReplacement->appendChild(
			$this->createTextNode( $node->ownerDocument, '<headertabs />', __METHOD__ )
		);

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
