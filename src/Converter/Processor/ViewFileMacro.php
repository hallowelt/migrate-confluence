<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMNode;
use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;

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
	 * @var array
	 */
	protected array $config;


	/**
	 * @param ConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 * @param string $rawPageTitle
	 * @param array $config
	 */
	public function __construct( ConversionDataLookup $dataLookup,
		int $currentSpaceId, string $rawPageTitle, array $config ) {
		$this->dataLookup = $dataLookup;
		$this->currentSpaceId = $currentSpaceId;
		$this->rawPageTitle = $rawPageTitle;
		$this->config = $config;
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

		// No ri:filename attribute at all — macro is genuinely broken.
		if ( !isset( $params['_riFilename'] ) ) {
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( $this->getBrokenMacroCategory() ),
				$node
			);
			return;
		}

		$riFilename = $params['_riFilename'];
		unset( $params['_riFilename'] );

		$filenameResolver = new FilenameResolver( $this->dataLookup, $this->config );
		[ 'title' => $targetFilename, 'isBroken' => $isBrokenLink ] =
			$filenameResolver->resolve( $this->currentSpaceId, $this->rawPageTitle, $riFilename );


		// Insert filename first so the template renders params in the expected order.
		$params = array_merge( [ 'filename' => $targetFilename ], $params );

		$wikitextTemplate = new Template( $this->getWikiTextTemplateName(), $params );
		$wikitextTemplate->setRenderFormatted( false );
		$text = $wikitextTemplate->render();
		if ( $isBrokenLink ) {
			$text .= '[[Category:Broken_attachment_link]]';
		}

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $text ),
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
