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
	 *
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		foreach ( $dom->getElementsByTagName( 'structured-macro' ) as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) !== static::MACRO_NAME ) {
				continue;
			}
			foreach ( $macro->childNodes as $child ) {
				if ( $child instanceof DOMElement && $child->localName === 'parameter'
					&& $child->getAttribute( 'ac:name' ) === 'page' && trim( $child->nodeValue ) !== '' ) {
					$child->nodeValue = htmlspecialchars( $this->resolveWikiTitle( trim( $child->nodeValue ) ) );
				}
			}
		}
		parent::process( $dom );
	}

	/**
	 * @param string $page Confluence page title, optionally prefixed with "SPACEKEY:"
	 * @return string
	 */
	private function resolveWikiTitle( string $page ): string {
		$spaceId = $this->currentSpaceId;
		$title = $page;
		$colon = strpos( $page, ':' );
		if ( $colon !== false ) {
			$otherSpaceId = $this->dataLookup->getSpaceIdFromSpaceKey( substr( $page, 0, $colon ) );
			if ( $otherSpaceId !== null ) {
				$spaceId = $otherSpaceId;
				$title = substr( $page, $colon + 1 );
			}
		}

		return $this->dataLookup->getWikiPageTitleForLink( $this->currentSpaceId, $spaceId, $title )
			// Unresolvable: keep a visible red link, as the other page-referencing macros do
			?? $this->getConfluencePageKeyFromSpaceId( $spaceId, $title );
	}

	/**
	 * @inheritDoc
	 */
	protected function getWikiTextTemplateName(): string {
		return 'PrivacyPolicy';
	}
}
