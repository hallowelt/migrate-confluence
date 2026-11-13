<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

/**
 * <ac:structured-macro ac:name="privacy-policy">
 * 	<ac:parameter ac:name="page">Privacy Policy</ac:parameter>
 * 	<ac:rich-text-body>Short statement</ac:rich-text-body>
 * </ac:structured-macro>
 */
class PrivacyPolicyMacro extends ConvertMacroToTemplateBase {

	public const MACRO_NAME = 'privacy-policy';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_ALMOST_FULLY;

	/**
	 * @param IConverterDataWriter $writer
	 * @param int $currentSpaceId
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		IConverterDataWriter $writer,
		private int $currentSpaceId,
		private DBConversionDataLookup $dataLookup
	) {
		parent::__construct( $writer, $currentSpaceId );
	}

	/**
	 * Resolves a given `page` parameter to its wiki title; without one, the template default applies.
	 * Unresolvable pages stay visible as red links and get the broken-macro category.
	 *
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		foreach ( iterator_to_array( $dom->getElementsByTagName( 'structured-macro' ) ) as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) !== static::MACRO_NAME ) {
				continue;
			}
			foreach ( $macro->childNodes as $child ) {
				if ( !$child instanceof DOMElement || $child->localName !== 'parameter'
					|| $child->getAttribute( 'ac:name' ) !== 'page' ) {
					continue;
				}
				[ $spaceKey, $title ] = $this->readPageReference( $child );
				if ( $title === '' ) {
					continue;
				}
				$wikiTitle = $this->resolveWikiTitle( $spaceKey, $title );
				if ( $wikiTitle === null ) {
					$wikiTitle = $this->getConfluencePageKeyFromSpaceId( $this->spaceIdFor( $spaceKey ), $title );
					$macro->parentNode->insertBefore(
						$this->createTextNode( $dom, $this->getCategoryBrokenMacro( static::MACRO_NAME ), __METHOD__ ),
						$macro
					);
				}
				$child->nodeValue = htmlspecialchars( $wikiTitle );
				break;
			}
		}
		parent::process( $dom );
	}

	/**
	 * Reads a page reference, either as `ri:page` element (with optional `ri:space-key`) or as plain text,
	 * where the title may be prefixed with "SPACEKEY:".
	 *
	 * @param DOMElement $param
	 * @return array{0: string, 1: string} Space key (empty for the current space) and title
	 */
	private function readPageReference( DOMElement $param ): array {
		foreach ( $param->childNodes as $node ) {
			if ( $node instanceof DOMElement && $node->localName === 'page' ) {
				return [ $node->getAttribute( 'ri:space-key' ), trim( $node->getAttribute( 'ri:content-title' ) ) ];
			}
		}
		$page = trim( $param->nodeValue );
		$colon = strpos( $page, ':' );
		if ( $colon !== false && $this->dataLookup->getSpaceIdFromSpaceKey( substr( $page, 0, $colon ) ) !== null ) {
			return [ substr( $page, 0, $colon ), substr( $page, $colon + 1 ) ];
		}
		return [ '', $page ];
	}

	/**
	 * @param string $spaceKey
	 * @return int|null
	 */
	private function spaceIdFor( string $spaceKey ): ?int {
		return $spaceKey === '' ? $this->currentSpaceId : $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
	}

	/**
	 * @param string $spaceKey
	 * @param string $title
	 * @return string|null
	 */
	private function resolveWikiTitle( string $spaceKey, string $title ): ?string {
		$spaceId = $this->spaceIdFor( $spaceKey );
		if ( $spaceId === null ) {
			return null;
		}
		return $this->dataLookup->getWikiPageTitleForLink( $this->currentSpaceId, $spaceId, $title );
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'PrivacyPolicy';
	}
}
