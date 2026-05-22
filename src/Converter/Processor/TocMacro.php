<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;

class TocMacro extends StructuredMacroProcessorBase {

	/** @var TocMacroUsage */
	private TocMacroUsage $usage;

	public function __construct( TocMacroUsage $usage ) {
		$this->usage = &$usage;
	}

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
		$this->usage->tocIsUsed();

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( "\n__TOC__\n###BREAK###" ),
			$node
		);
	}
}
