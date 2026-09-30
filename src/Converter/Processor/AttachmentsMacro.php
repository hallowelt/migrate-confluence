<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

class AttachmentsMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'attachments';

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
