<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;

class AttachmentsMacro extends StructuredMacroProcessorBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'attachments';
	}

	/**
	 * @inheritDoc
	 *
	 * @param DOMElement $node
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$attachmentsEl = $node->ownerDocument->createElement( 'attachments' );
		$attachmentsEl->appendChild( $node->ownerDocument->createTextNode( '' ) );
		$node->parentNode->replaceChild( $attachmentsEl, $node );
	}
}
