<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\TocMacroUsage;

class TocMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'toc';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	/** @var TocMacroUsage */
	private TocMacroUsage $usage;

	public function __construct( TocMacroUsage $usage ) {
		$this->usage = &$usage;
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$this->usage->tocIsUsed();

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, "\n__TOC__\n###BREAK###", __METHOD__ ),
			$node
		);
	}
}
