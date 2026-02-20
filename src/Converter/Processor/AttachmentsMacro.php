<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

class AttachmentsMacro extends StructuredMacroProcessorBase {

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'attachments';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$attachmentsEl = $node->ownerDocument->createElement( 'attachments' );
		$attachmentsEl->appendChild( $node->ownerDocument->createTextNode( '' ) );
		$node->parentNode->replaceChild( $attachmentsEl, $node );
	}
}
