<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMNodeList;

/**
 * <ac:structured-macro ac:name="table-filter" ac:schema-version="1" ac:macro-id="...">
 *   <ac:parameter ac:name="inverse">,</ac:parameter>
 *	 <ac:parameter ac:name="sparkName">...</ac:parameter>
 *	 <ac:parameter ac:name="column">...</ac:parameter>
 *	 <ac:parameter ac:name="limitHeight" />
 *	 <ac:parameter ac:name="separator">...</ac:parameter>
 *	 <ac:parameter ac:name="labels">...</ac:parameter>
 *	 <ac:parameter ac:name="default">...</ac:parameter>
 *	 <ac:parameter ac:name="isFirstTimeEnter">...</ac:parameter>
 *	 <ac:parameter ac:name="cell-width">...</ac:parameter>
 *	 <ac:parameter ac:name="userfilter">...</ac:parameter>
 *	 <ac:parameter ac:name="datepattern">dd.mm.yy</ac:parameter>
 *	 <ac:parameter ac:name="id">...</ac:parameter>
 *	 <ac:parameter ac:name="updateSelectOptions">...</ac:parameter>
 *	 <ac:parameter ac:name="worklog">365|5|8|y w d h m|y w d h m</ac:parameter>
 *	 <ac:parameter ac:name="isOR">AND</ac:parameter>
 *	 <ac:parameter ac:name="order">0,1</ac:parameter>
 *	 <ac:rich-text-body>
 *     ...
 *   </ac:rich-text-body>
 * </ac:structured-macro>
 */
class TableFilterMacro extends ConvertMacroToTemplateBase {

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'table-filter';
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'TableFilterMacro';
	}

	/**
	 * @inheritDoc
	 */
	protected function extractBodyElements( DOMElement $actualMacro, DOMElement $parentNode ): void {
		/** @var DOMNodeList $richTextBodies */
		$richTextBodies = $actualMacro->getElementsByTagName( 'rich-text-body' );
		$richTextBodyEls = [];
		foreach ( $richTextBodies as $richTextBody ) {
			$richTextBodyEls[] = $richTextBody;
		}

		if ( !empty( $richTextBodyEls ) ) {
			$bodyString = "|body = ";
			if ( $this->addLinebreakInsideTemplate() ) {
				$bodyString .= "###BREAK###\n";
			}
			$wikitextTemplateEndTextNode = $actualMacro->ownerDocument->createTextNode( $bodyString );
			$parentNode->insertBefore( $wikitextTemplateEndTextNode, $actualMacro );

			for ( $index = 0; $index < $richTextBodies->length; $index++ ) {
				$bodyEl = $richTextBodies->item( $index );

				foreach ( $bodyEl->childNodes as $childNode ) {
					if ( $childNode instanceof DOMElement === false ) {
						continue;
					}
					if ( $childNode->nodeName !== 'table' ) {
						continue;
					}

					$classes = [];
					if ( $childNode->hasAttribute( 'class' ) ) {
						$class = $childNode->getAttribute( 'class' );
						$classes = explode( ' ', $class );
					}
					$classes[] = 'datagrid';

					$childNode->setAttribute( 'class', implode( ' ', $classes ) );

					$parentNode->insertBefore( $childNode, $actualMacro );
				}
			}
		}
	}
}
