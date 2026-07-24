<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use Exception;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ExcerptIncludeMacro extends StructuredMacroProcessorBase {

	/** @var ConversionHelper */
	private ConversionHelper $conversionHelper;

	/** @var bool */
	private bool $isBroken;

	/**
	 * @param DBConversionDataLookup $dataLookup
	 * @param int $currentSpaceId
	 */
	public function __construct(
		private readonly DBConversionDataLookup $dataLookup,
		private readonly int $currentSpaceId
	) {
		$this->conversionHelper = new ConversionHelper();
	}

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'excerpt-include';
	}

	/**
	 * @inheritDoc
	 * @throws Exception
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$this->isBroken = false;

		$macroReplacement = $node->ownerDocument->createElement( 'excerpt-include' );
		if ( !$macroReplacement ) {
			throw new Exception( 'Could not create excerpt-include element' );
		}

		$targetPage = $this->findPageParameter( $node );
		if ( $targetPage ) {
			$macroReplacement->setAttribute( 'page', $targetPage );
		}

		$macroReplacement->setAttribute( 'data-foo', 'bar' );

		$options = $this->findOptionsParameters( $node );
		$macroReplacement->setAttribute( 'showpanel', !!$options['nopanel'] ? "true" : "false" );

		if ( !empty( $options['name'] ) ) {
			$macroReplacement->setAttribute( 'excerpt', $options['name'] );
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );

		if ( $this->isBroken ) {
			$macroReplacement->after(
				$this->createTextNode(
					$macroReplacement->ownerDocument,
					$this->getBrokenMacroCategory(),
					__METHOD__
				)
			);
			$this->isBroken = false;
		}
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return array
	 */
	private function findOptionsParameters( DOMElement $node ): array {
		$options = [
			'nopanel' => true,
			'name' => null
		];

		foreach ( $node->getElementsByTagName( 'parameter' ) as $parameter ) {
			$parameterName = $parameter->getAttribute( 'ac:name' );
			$paramValue = $parameter->textContent;

			if ( ( $parameterName !== "nopanel" && $parameterName !== "name" ) || empty ( $paramValue ) ) {
				continue;
			}

			$options[$parameterName] = trim( $paramValue );
		}

		return $options;
	}

	/**
	 * @param DOMElement $node
	 *
	 * Target page is in default parameter.
	 * Either ac:name="" or ac:default-parameter
	 *
	 * @return string|null
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

		$this->isBroken = true;
		return null;
	}

	/**
	 * Resolve the referenced page title from an excerpt-include default parameter.
	 *
	 * The parameter either wraps an <ac:link><ri:page ri:content-title="…"/></ac:link>
	 * or holds the page title as plain text.
	 *
	 * @param DOMElement $pageElement
	 *
	 * @return string|null The wiki page title, or null if it can't be determined.
	 * @throws Exception
	 */
	private function findPageValue( DOMElement $pageElement ): ?string {
		$pageLinkElement = $pageElement->getElementsByTagName( 'link' )->item( 0 );
		if ( $pageLinkElement instanceof DOMElement ) {
			$pageLinkPageElement = $pageLinkElement->getElementsByTagName( 'page' )->item( 0 );
			if ( $pageLinkPageElement instanceof DOMElement ) {
				$confluenceTitle = $pageLinkPageElement->getAttribute( 'ri:content-title' );
				if ( empty ( $confluenceTitle ) ) {
					$this->isBroken = true;
					return null;
				}

				return $this->getWikiPageTitle(
					$confluenceTitle,
					$pageLinkPageElement
				);
			}
		}

		$confluenceTitle = $pageElement->textContent;
		if ( empty ( $confluenceTitle ) ) {
			$this->isBroken = true;
			return null;
		}

		return $this->getWikiPageTitle( $confluenceTitle, $pageElement );
	}

	/**
	 * @param string $confluenceTitle
	 * @param DOMElement $el
	 *
	 * @return string
	 * @throws Exception
	 */
	private function getWikiPageTitle( string $confluenceTitle, DOMElement $el ): string {
		$spaceId = null;
		$spaceKey = $el->getAttribute( 'ri:space-key' );

		if ( !empty ( $spaceKey ) ) {
			$spaceId = $this->dataLookup->getSpaceIdFromSpaceKey( $spaceKey );
		}

		if ( !$spaceId ) {
			$spaceId = $this->currentSpaceId;
		}

		if ( $wikiTitle = $this->dataLookup->getWikiPageTitleFromSpaceId( $spaceId, $confluenceTitle ) ) {
			return $wikiTitle;
		}

		// Fallback to confluence page key
		$this->isBroken = true;
		if ( empty( $spaceKey ) ) {
			return $this->conversionHelper->getConfluencePageKeyFromSpaceId( $spaceId, $confluenceTitle );
		}

		return $this->conversionHelper->getConfluencePageKeyFromSpaceKey( $spaceKey, $confluenceTitle );
	}
}
