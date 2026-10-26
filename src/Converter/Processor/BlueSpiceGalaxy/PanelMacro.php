<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor\BlueSpiceGalaxy;

use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroProcessorBase;
use HalloWelt\MigrateConfluence\Utility\MacroInfo;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

/**
 * Converts the Confluence Panel macro into a BlueSpice card droplet
 */
class PanelMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	public const MACRO_NAME = 'panel';

	public const SUPPORT_LEVEL = MacroInfo::SUPPORT_LEVEL_PARTIALLY;

	public const REQUIRED_EXTENSIONS = [ 'StandardTags' ];

	private array $iconMap = [];

	public function __construct(
		private readonly PlaceholderManager $placeholderManager
	) {
		$this->iconMap = require dirname( dirname( __DIR__ ) ) . '/_data/emoji_short_names.php';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$title = '';
		$bgColor = null;
		$panelIconText = '';
		$richTextBody = null;

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement === false ) {
				continue;
			}

			if ( $childNode->nodeName === 'ac:rich-text-body' ) {
				$richTextBody = $childNode;
				continue;
			}

			if ( $childNode->nodeName !== 'ac:parameter' ) {
				continue;
			}

			switch ( $childNode->getAttribute( 'ac:name' ) ) {
				case 'title':
					$title = trim( $childNode->nodeValue );
					break;
				case 'bgColor':
					$bgColor = $this->getBgColor( trim( $childNode->nodeValue ) );
					break;
				case 'panelIconText':
					$panelIconText = $this->getIcon( trim( $childNode->nodeValue ) );
					break;
			}
		}

		$parent = $node->parentNode;

		$startTag = $this->createTextNode(
			$node->ownerDocument,
			$this->placeholderManager->getPlaceholder( sprintf(
				'<div class="ext-standardtags-card"%s>' .
					'<div class="ext-standardtags-card-header">' .
						'%s' .
						'<div class="ext-standardtags-card-headings">' .
							'<div class="ext-standardtags-card-title">%s</div>' .
						'</div>' .
					'</div>' .
					'<div class="ext-standardtags-card-body">',
				$bgColor ? sprintf( ' style="--st-card-accent: %s"', $bgColor ) : '',
				$panelIconText ? sprintf(
					'<div class="ext-standardtags-card-icon bi" aria-hidden="true">%s</div>',
					$panelIconText
				) : '',
				htmlspecialchars( $title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) ),
			) . "\n",
			__METHOD__
		);

		$frag = $node->ownerDocument->createDocumentFragment();
		$frag->appendChild( $startTag );

		if ( $richTextBody !== null ) {
			foreach ( $richTextBody->childNodes as $childNode ) {
				$frag->appendChild( $childNode->cloneNode( true ) );
			}
		}

		$frag->appendChild( $this->createTextNode(
			$node->ownerDocument,
			"\n" . $this->placeholderManager->getPlaceholder( "</div></div>\n" ),
			__METHOD__
		) );
		$parent->replaceChild( $frag, $node );
	}

	/**
	 * Map a named color to the Confluence set instead of CSS color names
	 *
	 * @param string $color the original color value
	 * @return string the mapped color or the original
	 */
	private function getBgColor( string $color ): string {
		$color = match ( $color ) {
			"blue" => "#E7F9FF",
			"green" => "#DCFFF1",
			"yellow" => "#FEF7C8",
			"orange" => "#FFE8CC",
			"purple" => "#F8EEFE",
			"grey" => "#F2F3F9",
			default => $color,
		};
		return $color;
	}

	/**
	 * Map an icon, if needed, from its short name to the corresponding emoji
	 *
	 * Otherwise return the value unchanged, assuming it is already an emoji.
	 *
	 * @param string $icon
	 * @return string
	 */
	private function getIcon( string $icon ): string {
		if ( isset( $this->iconMap[ $icon ] ) ) {
			return $this->iconMap[ $icon ];
		}
		return $icon;
	}
}
