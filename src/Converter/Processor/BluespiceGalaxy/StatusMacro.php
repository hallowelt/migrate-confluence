<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BluespiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroProcessorBase;

/**
 * Convert into <status>
 *
 * <ac:structured-macro ac:name="status" ac:schema-version="1" ac:macro-id="1b880702-ef9e-4f6c-be5d-717c6e4cdaae">
 *   <ac:parameter ac:name="title">Good Status</ac:parameter>
 *   <ac:parameter ac:name="colour">Red</ac:parameter>
 * </ac:structured-macro>
 */
class StatusMacro extends StructuredMacroProcessorBase {

	/**
	 * @inheritDoc
	 */
	protected function getMacroName(): string {
		return 'status';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = [
			'color' => 'neutral',
			'light' => 'false',
		];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ||
			     $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			$name = $childNode->getAttribute( 'ac:name' );
			$value = $childNode->nodeValue;
			switch ( $name ) {
				case 'colour':
					$params['color'] = strtolower( $value );
					break;
				case 'subtle':
					$params['light'] = strtolower( $value );
					break;
				case 'title':
					$params['title'] = strtoupper( $value );
					break;
			}
		}
		if ( !isset( $params['title'] ) ) {
			$params['title'] = $params['color'];
		}

		$statusTag = $this->createTextNode(
			$node->ownerDocument,
			sprintf(
				'#####STATUSOPEN color="%s" light="%s"#####%s#####STATUSCLOSE#####',
				$params['color'] ?? '',
				$params['light'] ?? 'false',
				$params['title'] ?? $params['color'] ?? ''
			),
			__METHOD__
		);
		$node->parentNode->insertBefore( $statusTag, $node );
		$node->parentNode->removeChild( $node );
	}
}
