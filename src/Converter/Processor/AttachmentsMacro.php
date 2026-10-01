<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class AttachmentsMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'attachments';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'EnhancedUpload' ];

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$attachmentsEl = $node->ownerDocument->createElement( 'attachments' );
		$attachmentsEl->appendChild(
			$this->createTextNode( $node->ownerDocument, '', __METHOD__ )
		);
		$node->parentNode->replaceChild( $attachmentsEl, $node );
	}
}
