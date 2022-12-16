<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class StructuredMacroToc extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'toc';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( "\n__TOC__\n###BREAK###" ),
			$node
		);
	}
}
