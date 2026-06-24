<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

class ViewFileMacro extends StructuredMacroProcessorBase {

	/**
	 * @var DBConversionDataLookup
	 */
	protected DBConversionDataLookup $dataLookup;

	/**
	 * @var int
	 */
	protected int $currentSpaceId;

	/**
	 * @var string
	 */
	protected string $rawPageTitle;

	/**
	 * @var MigrationConfig
	 */
	protected MigrationConfig $migrationConfig;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct( DBConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle, MigrationConfig $migrationConfig ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->migrationConfig = $migrationConfig;
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
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->readParams( $node );

		// No ri:filename attribute at all — macro is genuinely broken.
		if ( !isset( $params['_riFilename'] ) ) {
			$node->parentNode->replaceChild(
				$this->createTextNode(
					$node->ownerDocument,
					$this->getBrokenMacroCategory(),
					__METHOD__
				),
				$node
			);
			return;
		}

		$riFilename = $params['_riFilename'];
		unset( $params['_riFilename'] );

		$filenameResolver = new FilenameResolver( $this->dataLookup, $this->migrationConfig );
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenLink ] =
			$filenameResolver->resolve( $this->currentSpaceId, $this->rawPageTitle, $riFilename );

		// Insert filename first so the template renders params in the expected order.
		$params = array_merge( [ 'filename' => $targetFilename ], $params );

		$wikitextTemplate = new Template( $this->getWikiTextTemplateName(), $params );
		$wikitextTemplate->setRenderFormatted( false );
		$text = $wikitextTemplate->render();
		if ( $isBrokenLink ) {
			$text .= $this->getCategoryBroken( 'attachment_link' );
		}

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, $text, __METHOD__ ),
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
	 * @param DOMElement $node
	 * @return array
	 */
	protected function readParams( DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				if ( $childNode instanceof DOMElement === false ) {
					continue;
				}
				$paramName = $childNode->getAttribute( 'ac:name' );
				if ( $paramName === 'name' ) {
					foreach ( $childNode->childNodes as $attachmentNode ) {
						if ( $attachmentNode instanceof DOMElement === false ) {
							continue;
						}
						if ( $attachmentNode->nodeName === 'ri:attachment' ) {
							if ( $attachmentNode->hasAttribute( 'ri:filename' ) ) {
								$params['_riFilename'] = $attachmentNode->getAttribute( 'ri:filename' );
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
}
