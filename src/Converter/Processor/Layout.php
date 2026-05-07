<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class Layout implements IProcessor {

	/**
	 * @return string
	 */
	protected function getTagName(): string {
		return 'layout';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateStylesName(): string {
		return 'Layout';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$templateStylesName = $this->getWikiTextTemplateStylesName();
		$templateStyles = '{{' . $templateStylesName . '}}';

		$layouts = [];
		$liveLayouts = $dom->getElementsByTagName(
			$this->getTagName()
		);
		foreach ( $liveLayouts as $layout ) {
			$layouts[] = $layout;
		}

		foreach ( $layouts as $layout ) {
			if ( $templateStylesName !== '' ) {
				$templateStylesEl = $layout->ownerDocument->createTextNode(
					$templateStyles
				);
				$layout->parentElement->insertBefore( $templateStylesEl, $layout );
			}

			$wikiLayoutEl = $dom->createElement( 'div' );
			$wikiLayoutEl->setAttribute( 'class', $this->getTagName() );

			$attributeNames = $layout->getAttributeNames();
			foreach ( $attributeNames as $attributeName ) {
				$value = $layout->getAttribute( $attributeName );
				if ( str_starts_with( $attributeName, 'ac:' ) ) {
					$attributeName = 'data-' . substr( $attributeName, 3 );
				}
				$wikiLayoutEl->setAttribute(
					$attributeName,
					$value
				);
			}

			// Create non live list
			$childNodes = [];
			foreach ( $layout->childNodes as $childNode ) {
				$childNodes[] = $childNode;
			}

			foreach ( $childNodes as $childNode ) {
				$wikiLayoutEl->append( $childNode );
			}

			$layout->parentElement->insertBefore( $wikiLayoutEl, $layout );

			$layout->remove();
		}
	}

}
