<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;

class TocMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'toc';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( "\n__TOC__\n###BREAK###" ),
			$node
		);
	}
}
