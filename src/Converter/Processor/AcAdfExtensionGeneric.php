<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * generic handler for all <ac:adf-extension> nodes that are not handled elsewhere
 *
 * Should be added at a late stage. The idea is to use <ac:adf-fallback> as a
 * fallback for unsupported extensions.
 *
 * Structure:
 *     <ac:adf-extension>
 *         <ac:node type="...">
 *             ...
 *         </ac:node>
 *         <ac:adf-fallback>
 *             ...
 *         </ac:adf-fallback>
 *     </ac:adf-extension>
 */
class AcAdfExtensionGeneric extends ConversionHelper implements IProcessor, IUsesPlaceholder {
	private const NS = 'some';

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		/* array_reverse() serves 2 purposes:
		 * 1. change the live node list to static array
		 * 2. process the nodes inside out, so that we handle children before parents
		 */
		$adfExtNodes = array_reverse( iterator_to_array( $dom->getElementsByTagNameNS( self::NS, 'adf-extension' ) ) );

		foreach ( $adfExtNodes as $node ) {
			/** @var DOMElement $node */
			$adfFallback = $node->getElementsByTagNameNS( self::NS, 'adf-fallback' )->item( 0 );
			if ( $adfFallback ) {
				$frag = $dom->createDocumentFragment();
				while ( $adfFallback->firstChild ) {
					$frag->appendChild( $adfFallback->firstChild );
				}
				$frag->appendChild( $dom->createTextNode( '[[Category:Migration/Adf_extension_fallback]]' ) );
				$node->parentNode->replaceChild( $frag, $node );
			} else {
				$replacement = $node->ownerDocument->createElement( 'div' );
				$type = 'unknown';
				$adfNode = $node->getElementsByTagNameNS( self::NS, 'adf-node' )->item( 0 );
				if ( $adfNode ) {
					$type = $adfNode->getAttribute( 'type' ) ?: 'unknown';
				}
				$replacement->setAttribute( 'class', 'ac-ext-' . $type );

				$replacement->appendChild(
					$this->createTextNode(
						$replacement->ownerDocument,
						$this->placeholderManager->getPlaceholder(
							"<!--" . $node->ownerDocument->saveXML( $node ) . "-->" ) .
							$this->getCategoryBroken( 'adf_extension' ),
						__METHOD__
					)
				);
				$node->parentNode->replaceChild( $replacement, $node );
			}
		}
	}
}
