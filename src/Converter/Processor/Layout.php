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
	protected function getWikiTextTemplateStartName(): string {
		return 'LayoutStart';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateEndName(): string {
		return 'LayoutEnd';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$templateStartName = $this->getWikiTextTemplateStartName();
		$templateEndName = $this->getWikiTextTemplateEndName();

		$layouts = [];
		$liveLayouts = $dom->getElementsByTagName(
			$this->getTagName()
		);
		foreach ( $liveLayouts as $layout ) {
			$layouts[] = $layout;
		}

		foreach ( $layouts as $layout ) {
			$attributeNames = $layout->getAttributeNames();

			$params = [];
			foreach ( $attributeNames as $attributeName ) {
				$paramName = $attributeName;
				if ( str_starts_with( $paramName, 'ac:' ) ) {
					$paramName = substr( $paramName, 3 );
				}
				$params[$paramName] = $layout->getAttribute( $attributeName );
			}

			$startTemplateText = "{{" . $templateStartName;
			if ( !empty( $params ) ) {
				$startTemplateText .= "###BREAK###\n";
			}
			foreach ( $params as $name => $value ) {
				$startTemplateText .= "| $name = $value###BREAK###\n";
			}
			$startTemplateText .= "}}";

			$openTemplate = $layout->ownerDocument->createTextNode(
				$startTemplateText
			);

			$layout->parentElement->insertBefore( $openTemplate, $layout );

			$childNodes = [];
			// Create non live list
			foreach ( $layout->childNodes as $childNode ) {
				$childNodes[] = $childNode;
			}

			foreach ( $childNodes as $childNode ) {
				$layout->parentElement->insertBefore( $childNode, $layout );
			}

			$endTemplateText = "{{" . $templateEndName . "}}";
			$closeTemplate = $layout->ownerDocument->createTextNode(
				$endTemplateText
			);

			$layout->parentElement->insertBefore( $closeTemplate, $layout );

			$layout->remove();
		}
	}

}
