<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * Base for the Table Filter macros that render an attached data file (CSV, JSON) as a table.
 * They are converted to a call of the parser function `#spreadsheettable` of the
 * SpreadsheetFileReader extension, which renders the whole file as an HTML table:
 *
 * {{#spreadsheettable:Some_page-Data.csv}}
 *
 * Only the data source "attachment" can be represented. Macros with another source (URL, body, ...)
 * or with an attachment that was not migrated are kept and marked as broken.
 *
 * Macro parameters other than "attachment" and "source" (e.g. "expand", "format", "isFirstTimeEnter")
 * have no equivalent and are not migrated.
 */
abstract class SpreadsheetTableMacroBase extends StructuredMacroProcessorBase {

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'SpreadsheetFileReader' ];

	public function __construct(
		private DBConversionDataLookup $dataLookup,
		private int $currentSpaceId,
		private string $rawPageTitle,
		private MigrationConfig $migrationConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = $this->getMacroParams( $node );
		$attachment = trim( $params['attachment'] ?? '' );
		$source = trim( $params['source'] ?? 'attachment' );

		if ( $attachment === '' || $source !== 'attachment' ) {
			$this->markBroken( $node );
			return;
		}

		$resolver = new FilenameResolver( $this->dataLookup, $this->migrationConfig );
		[ 'title' => $fileTitle, 'isBroken' => $isBroken ] =
			$resolver->resolve( $this->currentSpaceId, $this->rawPageTitle, $attachment );
		if ( $isBroken ) {
			// Attachment was not migrated, the file can't be read by the wiki
			$this->markBroken( $node );
			return;
		}

		$node->parentNode->replaceChild(
			$this->createTextNode( $node->ownerDocument, '{{#spreadsheettable:' . $fileTitle . '}}', __METHOD__ ),
			$node
		);
	}

	private function getMacroParams( DOMElement $node ): array {
		$params = [];
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof DOMElement && $child->nodeName === 'ac:parameter' ) {
				$params[$child->getAttribute( 'ac:name' )] = $child->nodeValue;
			}
		}
		return $params;
	}

	/**
	 * The macro is kept, so that "UnhandledMacroConverter" can show it at the end of the convert step.
	 */
	private function markBroken( DOMElement $node ): void {
		$category = $this->getCategoryBrokenMacro( static::MACRO_NAME );
		$node->parentNode->insertBefore( $this->createTextNode( $node->ownerDocument, $category, __METHOD__ ), $node );
	}
}
