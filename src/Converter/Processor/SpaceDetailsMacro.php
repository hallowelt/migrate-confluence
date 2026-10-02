<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class SpaceDetailsMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'space-details';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'Semantic_MediaWiki' ];

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 */
	public function __construct(
		private IConverterDataWriter $writer,
		private int $currentSpaceId
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->readParams( $node );
		$wikitextTemplate = new Template( $this->getWikiTextTemplateName(), $params );
		$wikitextTemplate->setRenderFormatted( false );
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode(
				$wikitextTemplate->render()
			),
			$node
		);

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			$this->getWikiTextTemplateName()
		);
	}

	protected function getWikiTextTemplateName(): string {
		return 'SpaceDetails';
	}

	/**
	 * @param DOMElement $node
	 * @return array
	 */
	protected function readParams( DOMElement $node ): array {
		$params = [];
		$params['width'] = '100%';

		foreach ( $node->childNodes as $paramNode ) {
			if ( $paramNode instanceof DOMElement === false ) {
				continue;
			}
			if ( $paramNode->nodeName === 'ac:parameter' ) {
				$paramName = $paramNode->getAttribute( 'ac:name' );
				if ( $paramName === 'width' ) {
					if ( trim( $paramNode->nodeValue ) !== '' ) {
						$params['width'] = trim( $paramNode->nodeValue );
					}
				}
			}
		}
		return $params;
	}
}
