<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * `pandoc` eats unknown tags like <markdown>, so the tag is hidden behind a placeholder.
 */
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

		/* The export contains escaped code fences (\`\`\`), which WikiMarkdown would not treat as fences */
		$markdownContent = preg_replace_callback(
			'/^([ \t]*)((?:\\\\`){3,})/m',
			static fn ( array $m ): string => $m[1] . str_replace( '\\`', '`', $m[2] ),
			$markdownContent
		);

		if ( $markdownContent === '' ) {
			$brokenMacro = true;
		}

		$replacement = null;

		if ( !$brokenMacro ) {
			$replacement = $node->ownerDocument->createTextNode(
				$this->placeholderManager->getPlaceholder( "<markdown>$markdownContent</markdown>" )
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
