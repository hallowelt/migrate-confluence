<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class MarkdownMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'markdown';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'WikiMarkdown' ];

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$brokenMacro = false;

		$markdownContent = '';
		$plainTextBodies = $node->getElementsByTagName( 'plain-text-body' );
		foreach ( $plainTextBodies as $plainTextBody ) {
			$markdownContent = $plainTextBody->nodeValue;
			break;
		}

		if ( $markdownContent === '' ) {
			$brokenMacro = true;
		}

		$replacement = null;

		if ( !$brokenMacro ) {
			// Markdown must reach the wiki verbatim, pandoc would convert or drop its markup
			$replacement = $this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( "<markdown>$markdownContent</markdown>" ),
				__METHOD__
			);
		}

		if ( $brokenMacro ) {
			$replacement = $this->createTextNode(
				$node->ownerDocument,
				$this->getBrokenMacroCategory(),
				__METHOD__
			);
		}

		$node->parentNode->replaceChild( $replacement, $node );
	}
}
