<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 *
 * <ac:macro ac:name="localtabgroup">
 * <ac:rich-text-body>
 * <ac:macro ac:name="localtab">
 * <ac:parameter ac:name="title">...</ac:parameter>
 * <ac:rich-text-body>...</ac:rich-text-body>
 * </ac:macro>
 * </ac:rich-text-body>
 * </ac:macro>
 */
class LocalTabGroupMacro extends MacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'localtabgroup';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_FULLY;

	public const REQUIRED_EXTENSIONS = [ 'Header_Tabs' ];

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct( private PlaceholderManager $placeholderManager ) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', "ac-localtabgroup" );

		$this->macroParams( $node, $macroReplacement );
		$this->macroBody( $node, $macroReplacement );
		// Append the "<headertabs />" tag
		$macroReplacement->appendChild(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( '<headertabs />' ),
				__METHOD__ )
		);

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
