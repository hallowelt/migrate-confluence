<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class ViewFileMacro extends StructuredMacroProcessorBase {

	/**
	 * @var ConversionDataLookup
	 */
	protected ConversionDataLookup $dataLookup;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	/**
	 * @var string
	 */
	protected string $rawPageTitle;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
	}

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'view-file';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMNode $node ): void {
		$params = $this->readParams( $node );
		$wikitextTemplate = new Template( $this->getWikiTextTemplateName(), $params );
		$wikitextTemplate->setRenderFormatted( false );
		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode(
				$wikitextTemplate->render()
			),
			$node
		);
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'ViewFile';
	}

	/**
	 * @param DOMNode $node
	 * @return array
	 */
	protected function readParams( DOMNode $node ): array {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === 'name' ) {
					foreach ( $childNode->childNodes as $attachmentNode ) {
						if ( $attachmentNode->nodeName === 'ri:attachment' ) {
							if ( $attachmentNode->hasAttribute( 'ri:filename' ) ) {
								$params['filename'] = $this->makeFilename(
									$attachmentNode->getAttribute( 'ri:filename' )
								);
							}
							if ( $attachmentNode->hasAttribute( 'ri:version-at-save' ) ) {
								$params['version-at-save'] = $attachmentNode
									->getAttribute( 'ri:version-at-save' );
							}
						}
					}
				} else {
					$paramValue = $childNode->nodeValue;
					$params[$paramName] = $paramValue;
				}
			}
		}
		return $params;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	private function makeFilename( string $name ): string {
		$spaceId = $this->currentSpaceId;
		$rawPageTitle = basename( $this->rawPageTitle );

		$confluenceFileKey = str_replace( ' ', '_', "$spaceId---$rawPageTitle---" . $name );
		$filename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );

		return $filename;
	}

}
