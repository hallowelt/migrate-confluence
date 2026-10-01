<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use Exception;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;

class ExcerptIncludeMacro extends StructuredMacroProcessorBase {

	public const MACRO_NAME = 'excerpt-include';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_SKETCHY;

	public const REQUIRED_EXTENSIONS = [ 'ParserFunctions' ];

	private ConversionHelper $conversionHelper;

	public function __construct(
		private IConverterDataWriter $writer,
		private readonly DBConversionDataLookup $dataLookup,
		private readonly int $currentSpaceId
	) {
		$this->conversionHelper = new ConversionHelper();
	}

	/**
	 * Is broken per default
	 *
	 * @inheritDoc
	 * @throws Exception
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$targetPage = $this->findPageParameter( $node );
		$options = $this->findOptionsParameters( $node );

		$showpanel = ( $options['nopanel'] === "true" ) ? "false" : "true";
		$excerptName = $options['name'];

		$params = [];
		if ( $targetPage !== null ) {
			$params[] = "page = $targetPage";
		}
		$params[] = "showpanel = $showpanel";
		if ( $excerptName !== null ) {
			$params[] = "excerpt = $excerptName";
		}

		$replacement = '{{ExcerptInclude|' . implode( '|', $params ) . '}}' . $this->getBrokenMacroCategory();

		$node->parentNode->replaceChild( $this->createTextNode(
			$node->ownerDocument,
			$replacement,
			__METHOD__
		), $node );

		$this->writer->registerDefaultPage(
			$this->currentSpaceId,
			"ExcerptInclude"
		);
	}

	private function findOptionsParameters( DOMElement $node ): array {
		$options = [
			'nopanel' => true,
			'name' => null
		];

		foreach ( $node->getElementsByTagName( 'parameter' ) as $parameter ) {
			$parameterName = $parameter->getAttribute( 'ac:name' );
			$paramValue = $parameter->textContent;

			if ( ( $parameterName !== "nopanel" && $parameterName !== "name" ) || empty( $paramValue ) ) {
				continue;
			}

			$options[$parameterName] = trim( $paramValue );
		}

		return $options;
	}

	/**
	 * Target page is in default parameter.
	 * Either ac:name="" or ac:default-parameter
	 * @throws Exception
	 */
	private function findPageParameter( DOMElement $node ): ?string {
		$defaultParameterElement = $node->getElementsByTagName( 'default-parameter' )->item( 0 );
		if ( $defaultParameterElement ) {
			return $this->findPageValue( $defaultParameterElement );
		}

		foreach ( $node->getElementsByTagName( 'parameter' ) as $parameterElement ) {
			$nameParameter = $parameterElement->getAttribute( 'ac:name' );
			if ( $nameParameter === "" ) {
				return $this->findPageValue( $parameterElement );
			}
		}

		return null;
	}

	/**
	 * Resolve the referenced page title from an excerpt-include default parameter.
	 *
	 * The parameter either wraps an <ac:link><ri:page ri:content-title="…"/></ac:link>
	 * or holds the page title as plain text.
	 *
	 * @throws Exception
	 */
	private function findPageValue( DOMElement $pageElement ): ?string {
		$pageLinkElement = $pageElement->getElementsByTagName( 'link' )->item( 0 );
		if ( $pageLinkElement instanceof DOMElement ) {
			$pageLinkPageElement = $pageLinkElement->getElementsByTagName( 'page' )->item( 0 );
			if ( $pageLinkPageElement instanceof DOMElement ) {
				$confluenceTitle = $pageLinkPageElement->getAttribute( 'ri:content-title' );
				if ( empty( $confluenceTitle ) ) {
					return null;
				}

				return $this->getWikiPageTitle(
					$confluenceTitle,
					$pageLinkPageElement
				);
			}
		}

		$confluenceTitle = $pageElement->textContent;
		if ( empty( $confluenceTitle ) ) {
			return null;
		}

		return $this->getWikiPageTitle( $confluenceTitle, $pageElement );
	}

	/**
	 * @throws Exception
	 */
	private function getWikiPageTitle( string $confluenceTitle, DOMElement $el ): string {
		$spaceId = null;
		$spaceKey = $el->getAttribute( 'ri:space-key' );

		if ( !empty( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		if ( !$spaceId ) {
			$spaceId = $this->currentSpaceId;
		}

		$wikiTitle = $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle );
		if ( $wikiTitle ) {
			return $wikiTitle;
		}

		// Fallback to confluence page key
		if ( empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $spaceId, $confluenceTitle );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $spaceKey, $confluenceTitle );
	}
}
