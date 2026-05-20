<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MediaWiki\Lib\WikiText\Template;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

// phpcs:disable Generic.Files.LineLength.TooLong

/**
 * <ac:structured-macro ac:name="create-from-template" ac:schema-version="1"
 * ac:macro-id="9c4e10cc-3728-409e-b178-16a9e2751ae8">
 *    <ac:parameter ac:name="templateName">12189714</ac:parameter>
 *    <ac:parameter ac:name="templateId">12189714</ac:parameter>
 *    <ac:parameter ac:name="buttonLabel">Create new Idea</ac:parameter>
 * </ac:structured-macro>
 *
 * <ac:structured-macro ac:name="create-from-template" ac:schema-version="1"
 * ac:macro-id="68860c88-2cb6-47fa-bb6c-afd9d0fdbdef">
 *    <ac:parameter ac:name="templateName">14516247</ac:parameter>
 *    <ac:parameter ac:name="templateId">14516247</ac:parameter>
 *    <ac:parameter ac:name="buttonLabel">New Glossary Entry</ac:parameter>
 * </ac:structured-macro>
 *
 * <ac:structured-macro ac:name="create-from-template" ac:schema-version="1"
 * ac:macro-id="f1bcfd9e-b015-4b0e-9237-35e1737343c1">
 *    <ac:parameter
 * ac:name="blueprintModuleCompleteKey">com.atlassian.confluence.plugins.confluence-business-blueprints:file-list-blueprint</ac:parameter>
 *    <ac:parameter ac:name="contentBlueprintId">f575164b-3d51-488f-ac65-586969e5a116</ac:parameter>
 *    <ac:parameter ac:name="templateName">f575164b-3d51-488f-ac65-586969e5a116</ac:parameter>
 *    <ac:parameter ac:name="createResult">view</ac:parameter>
 *    <ac:parameter ac:name="buttonLabel">Create File List</ac:parameter>
 * </ac:structured-macro>
 */
class CreateFromTemplateMacro implements IProcessor {

	/**
	 * @var ConversionDataLookup
	 */
	private ConversionDataLookup $dataLookup;

	/**
	 * @param ConversionDataLookup $dataLookup
	 */
	public function __construct( ConversionDataLookup $dataLookup ) {
		$this->dataLookup = $dataLookup;
	}

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'create-from-template';
	}

	/**
	 * @return string
	 */
	protected function getWikiTextTemplateName(): string {
		return 'CreateFromTemplate';
	}

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$structuredMacros = $dom->getElementsByTagName( 'structured-macro' );

		$macros = [];
		foreach ( $structuredMacros as $structuredMacro ) {
			$macros[] = $structuredMacro;
		}

		$macroName = $this->getMacroName();

		foreach ( $macros as $macro ) {
			if ( $macro->getAttribute( 'ac:name' ) === $macroName ) {
				$this->doProcessMacro( $macro );
			}
		}
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return void
	 */
	private function doProcessMacro( DOMNode $node ): void {
		$params = $this->getParams( $node );
		$templateTitle = $this->findTemplateTitle( $params );
		$errorMessage = $this->getResolutionError( $params, $templateTitle );

		$templateParams = [
			'preload' => $templateTitle ?? '',
			'buttonlabel' => $params['buttonLabel'] ?? '',
		];

		$wikiTemplate = new Template( $this->getWikiTextTemplateName(), $templateParams );
		$wikiTemplate->setRenderFormatted( false );
		$wikiText = $wikiTemplate->render();

		if ( $errorMessage !== null ) {
			$wikiText .= sprintf(
				"<!-- %s -->\n%s",
				$errorMessage,
				$this->getBrokenMacroCategory()
			);
		}

		$node->parentNode->replaceChild(
			$node->ownerDocument->createTextNode( $wikiText ),
			$node
		);
	}

	/**
	 * @param array $params
	 *
	 * @return string|null
	 */
	private function findTemplateTitle( array $params ): ?string {
		if ( !isset( $params['templateId'] ) ) {
			return null;
		}

		return $this->dataLookup->getTemplateTitleFromTemplateId( (int)$params['templateId'] );
	}

	/**
	 * @param array $params
	 * @param string|null $templateTitle
	 *
	 * @return string|null
	 */
	private function getResolutionError( array $params, ?string $templateTitle ): ?string {
		if ( $templateTitle !== null ) {
			return null;
		}

		if ( isset( $params['blueprintModuleCompleteKey'] ) && !isset( $params['templateId'] )
		) {
			$key = $params['blueprintModuleCompleteKey'];
			return "Template is a confluence blueprint or plugin and is not available (blueprintModuleCompleteKey: $key)";
		}

		if ( !isset( $params['templateId'] ) ) {
			return 'Template Id is missing';
		}

		$templateId = $params['templateId'];
		return "Template could not be found (templateId: $templateId)";
	}

	/**
	 * @return string
	 */
	private function getBrokenMacroCategory(): string {
		$macroName = $this->getMacroName();

		return "[[Category:Broken_macro/$macroName]]";
	}

	/**
	 * @param DOMNode $node
	 *
	 * @return array
	 */
	private function getParams( DOMNode $node ): array {
		$params = [];

		// Extract scalar parameters — only direct children, not those inside nested macros.
		$parameterEls = [];
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->localName === 'parameter' ) {
				$parameterEls[] = $child;
			}
		}
		foreach ( $parameterEls as $parameterEl ) {
			$paramName = $parameterEl->getAttribute( 'ac:name' );
			if ( trim( $paramName ) === '' ) {
				continue;
			}

			$params[$paramName] = $parameterEl->nodeValue;
		}

		return $params;
	}
}
