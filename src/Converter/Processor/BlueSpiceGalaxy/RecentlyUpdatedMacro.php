<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroProcessorBase;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Lists recently updated pages of the wiki with the StandardTags recentchanges tag.
 */
class RecentlyUpdatedMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'recently-updated';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'StandardTags' ];

	private const HANDLED_PARAMS = [ 'max', 'labels', 'hideHeading' ];

	/**
	 * @param PlaceholderManager $placeholderManager
	 */
	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$params = [];
		foreach ( $node->childNodes as $paramNode ) {
			if ( $paramNode instanceof DOMElement && $paramNode->nodeName === 'ac:parameter' ) {
				$params[$paramNode->getAttribute( 'ac:name' )] = trim( $paramNode->nodeValue );
			}
		}

		$attributes = [ 'source' => 'revision' ];
		if ( ctype_digit( $params['max'] ?? '' ) && (int)$params['max'] > 0 ) {
			$attributes['max'] = $params['max'];
		}
		if ( ( $params['labels'] ?? '' ) !== '' ) {
			$attributes['categories'] = $params['labels'];
		}
		if ( ( $params['hideHeading'] ?? '' ) === 'true' ) {
			$attributes['hideheading'] = 'true';
		}

		$wikiText = '<recentchanges';
		foreach ( $attributes as $name => $value ) {
			$wikiText .= " $name=\"" . str_replace( '"', '&quot;', $value ) . '"';
		}
		$wikiText .= ' />';

		$unhandled = [];
		foreach ( $params as $name => $value ) {
			if ( !in_array( $name, self::HANDLED_PARAMS, true ) ) {
				$unhandled[] = "$name=$value";
			}
		}
		if ( $unhandled ) {
			$wikiText .= '<!-- recently-updated macro parameters not migrated: '
				. str_replace( '--', '- -', implode( ', ', $unhandled ) ) . ' -->';
		}

		$node->parentNode->replaceChild(
			$this->createTextNode(
				$node->ownerDocument,
				$this->placeholderManager->getPlaceholder( $wikiText ),
				__METHOD__
			),
			$node
		);
	}
}
