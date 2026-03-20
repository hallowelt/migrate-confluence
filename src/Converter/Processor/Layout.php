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
	protected function getWikiTextTemplateName(): string {
		return 'Layout';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$templateName = $this->getWikiTextTemplateName();

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

			$openTemplateText = "{{" . $templateName . "###BREAK###\n";
			foreach ( $params as $name => $value ) {
				$openTemplateText .= "| $name = $value###BREAK###\n";
			}
			$openTemplateText .= "| body = ###BREAK###";

			$openTemplate = $layout->ownerDocument->createTextNode(
				$openTemplateText
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

			$closeTemplateText = "}}";
			$closeTemplate = $layout->ownerDocument->createTextNode(
				$closeTemplateText
			);

			$layout->parentElement->insertBefore( $closeTemplate, $layout );

			$layout->remove();
		}
	}

}
