<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class StructuredMacroViewFile extends StructuredMacroProcessorBase {

	/**
	 * @var ConversionDataLookup
	 */
	protected $dataLookup;

	/**
	 * @var int
	 */
	protected $currentSpaceId;

	/**
	 * @var string
	 */
	protected $rawPageTitle;

	/**
	 * @var bool
	 */
	protected $nsFileRepoCompat = false;

	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param bool $nsFileRepoCompat
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle, bool $nsFileRepoCompat = false ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->nsFileRepoCompat = $nsFileRepoCompat;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'view-file';
	}

	/**
	 * @param \DOMElement $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
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

	protected function getWikiTextTemplateName(): string {
		return 'ViewFile';
	}

	/**
	 * @param \DOMElement $node
	 * @return array
	 */
	protected function readParams( \DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === 'name' ) {
					foreach ( $childNode->childNodes as $attachmentNode ) {
						if ( $attachmentNode->nodeName === 'ri:attachment' ) {
							if ( $attachmentNode->hasAttribute( 'ri:filename' ) ) {
								$params['filename'] = $this->makeFilename( $attachmentNode->getAttribute( 'ri:filename' ) );				
							}
							if ( $attachmentNode->hasAttribute( 'ri:version-at-save' ) ) {
								$params['version-at-save'] = $attachmentNode->getAttribute( 'ri:version-at-save' );				
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
	private function makeFilename( string $name ) {
		$spaceId = $this->currentSpaceId;
		$rawPageTitle = basename( $this->rawPageTitle );

		$confluenceFileKey = "$spaceId---$rawPageTitle---" . str_replace( ' ', '_', $name );
		$filename = $this->dataLookup->getTargetFileTitleFromConfluenceFileKey( $confluenceFileKey );

		if ( $this->nsFileRepoCompat && $this->currentSpaceId !== 0 ) {
			$filenameParts = explode( '_', $filename );

			if ( count( $filenameParts ) > 2 ) {
				$filename = $filenameParts[0];
				array_shift( $filenameParts );
				$filename .= ':' . implode( '_', $filenameParts );
			}
		}

		return $filename;
	}

}
