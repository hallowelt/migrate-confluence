<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;

/**
 * Confluence stores emoticons/emoji reactions in two shapes:
 *
 * 1. Legacy wiki-markup emoticons, only carrying `ac:name`, e.g.
 *    <ac:emoticon ac:name="smile" />
 * 2. Modern emoji-picker reactions, which additionally carry
 *    `ac:emoji-shortname`, `ac:emoji-id` and `ac:emoji-fallback`, e.g.
 *    <ac:emoticon ac:name="blue-star" ac:emoji-shortname=":e-mail:"
 *      ac:emoji-id="1f4e7" ac:emoji-fallback="&#128231;" />
 *    `ac:name` for these is often the generic placeholder "blue-star",
 *    but may also be a descriptive slug (e.g. "thumbs-up", "heart").
 *
 * @see https://confluence.atlassian.com/doc/confluence-storage-format-790796544.html
 * @see standard.json, atlassian.json (emoji reference data shipped with this tool)
 */
class Emoticon extends ConversionHelper implements IProcessor {

	private const TEMPLATE_NAME = 'Emoticon';

	/**
	 * Background colors for Atlassian's numbered circle/square reaction
	 * icons (`ac:name` like "1_one_circle_yellow" or
	 * "4_four_square_purple"), which have no Unicode equivalent but can be
	 * recreated as a colored badge via the Emoticon template's `color`/
	 * `shape` params. Colors as specified by content owner, not derived from
	 * the (image-only) source data.
	 *
	 * @var array
	 */
	private const NUMBERED_ICON_COLORS = [
		'blue' => '#357de8',
		'gray' => '#7e828b',
		'green' => '#22a06b',
		'lime' => '#6a9a23',
		'magenta' => '#cd519d',
		'orange' => '#e06c00',
		'purple' => '#af59e1',
		'red' => '#e24a3f',
		'teal' => '#2999be',
		'yellow' => '#b38600',
	];

	/**
	 * Unicode replacements for the classic Confluence wiki-markup emoticons
	 * (23 notations, mapping to 22 distinct `ac:name` values, since
	 * thumbs-up/-down each have an upper- and lowercase notation), plus
	 * "heart" and "broken-heart" which also occur as bare `ac:name` values.
	 *
	 * ponytail: Unicode has no colour-specific star or "bulb off" glyph, so
	 * yellow-star/red-star/green-star/blue-star and light-on/light-off would
	 * all render the same generic glyph. To keep the rendered page
	 * unambiguous, the missing distinction is appended as plain text (e.g.
	 * "(yellow)"). Only applies to these bare legacy macros (no
	 * emoji-fallback attribute); anything with a real ac:emoji-fallback uses
	 * that value instead (see getTemplateParams()).
	 *
	 * @var array
	 */
	protected array $emoticonMapping = [
		// 🙂 :)
		'smile' => "\u{1F642}",
		// 🙁 :(
		'sad' => "\u{1F641}",
		// 😛 :P
		'cheeky' => "\u{1F61B}",
		// 😀 :D
		'laugh' => "\u{1F600}",
		// 😉 ;)
		'wink' => "\u{1F609}",
		// 👍 (y)
		'thumbs-up' => "\u{1F44D}",
		// 👎 (n)
		'thumbs-down' => "\u{1F44E}",
		// ℹ️ (i)
		'information' => "\u{2139}\u{FE0F}",
		// ✅ (/)
		'tick' => "\u{2705}",
		// ❌ (x)
		'cross' => "\u{274C}",
		// ⚠️ (!)
		'warning' => "\u{26A0}\u{FE0F}",
		// ➕ (+)
		'plus' => "\u{2795}",
		// ➖ (-)
		'minus' => "\u{2796}",
		// ❓ (?)
		'question' => "\u{2753}",
		// 💡 (on)
		'light-on' => "\u{1F4A1} (on)",
		// 💡 (off)
		'light-off' => "\u{1F4A1} (off)",
		// ⭐ (*)
		'yellow-star' => "\u{2B50} (yellow)",
		// ⭐ (*r)
		'red-star' => "\u{2B50} (red)",
		// ⭐ (*g)
		'green-star' => "\u{2B50} (green)",
		// ⭐ (*b) - only used without ac:emoji-fallback
		'blue-star' => "\u{2B50} (blue)",
		// 🚩 (flag)
		'flag' => "\u{1F6A9}",
		// 🏳️ (flagoff)
		'flag-off' => "\u{1F3F3}\u{FE0F}",
		// ❤️
		'heart' => "\u{2764}\u{FE0F}",
		// 💔
		'broken-heart' => "\u{1F494}",
	];

	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$processableLiveNodes = $dom->getElementsByTagName( 'emoticon' );

		$processableNodes = [];
		foreach ( $processableLiveNodes as $processableLiveNode ) {
			$processableNodes[] = $processableLiveNode;
		}

		foreach ( $processableNodes as $processableNode ) {
			$replacement = $this->getReplacement( $processableNode );
			$this->replaceEmoticon( $processableNode, $replacement );
		}
	}

	/**
	 * @param DOMElement $node
	 *
	 * @return string
	 */
	private function getReplacement( DOMElement $node ): string {
		$name = $node->getAttribute( 'ac:name' );
		$params = $this->getTemplateParams( $node, $name );

		if ( $params === null ) {
			// Keep the original markup, like UnhandledMacroConverter does for macros
			return '###HTMLCOMMENTOPEN###' . $node->ownerDocument->saveXML( $node ) . '###HTMLCOMMENTCLOSE###'
				. $this->getCategoryBroken( 'emoticon' );
		}

		return '{{' . self::TEMPLATE_NAME . $params . '}}';
	}

	/**
	 * @param DOMElement $node
	 * @param string $name
	 *
	 * @return string|null Template params string (e.g. "|char=X|alt=Y"), or
	 *   null if the emoticon can't be migrated
	 */
	private function getTemplateParams( DOMElement $node, string $name ): ?string {
		$fallback = $node->getAttribute( 'ac:emoji-fallback' );
		if ( $this->isUsableFallback( $fallback ) ) {
			return $this->buildParams( $fallback, $this->getAltText( $node, $name ) );
		}

		$numberedIcon = $this->getNumberedIconParams( $name );
		if ( $numberedIcon !== null ) {
			return $this->buildParams(
				$numberedIcon['char'],
				$this->getAltText( $node, $name ),
				$numberedIcon['color'],
				$numberedIcon['shape']
			);
		}

		if ( isset( $this->emoticonMapping[$name] ) ) {
			return $this->buildParams( $this->emoticonMapping[$name], $this->getAltText( $node, $name ) );
		}

		return null;
	}

	/**
	 * @param string $char
	 * @param string $altText
	 * @param string|null $color
	 * @param string|null $shape
	 * @return string
	 */
	private function buildParams(
		string $char, string $altText, ?string $color = null, ?string $shape = null
	): string {
		$params = '|char=' . $char;
		if ( $altText !== '' ) {
			$params .= '|alt=' . $altText;
		}
		if ( $color !== null ) {
			$params .= '|color=' . $color;
		}
		if ( $shape !== null ) {
			$params .= '|shape=' . $shape;
		}
		return $params;
	}

	/**
	 * Atlassian's numbered circle/square reaction icons follow the pattern
	 * "<number>_<spelled-out-number>_<circle|square>_<color>", e.g.
	 * "17_seventeen_circle_purple". These have no Unicode equivalent, but
	 * can be recreated as a colored badge.
	 *
	 * @param string $name
	 * @return array|null ['char' => number, 'shape' => circle|square, 'color' => hex], or null
	 */
	private function getNumberedIconParams( string $name ): ?array {
		if ( !preg_match( '/^(\d+)_[a-z]+_(circle|square)_([a-z]+)$/', $name, $matches ) ) {
			return null;
		}
		[ , $number, $shape, $colorName ] = $matches;
		$color = self::NUMBERED_ICON_COLORS[$colorName] ?? null;
		if ( $color === null ) {
			return null;
		}

		return [ 'char' => $number, 'shape' => $shape, 'color' => $color ];
	}

	/**
	 * Atlassian's image-only custom reaction icons (company logos, numbered
	 * circles/squares, etc.) report their own shortname back as `fallback`,
	 * e.g. ":light_bulb_on:", instead of a real Unicode character. Those
	 * can't be migrated without images.
	 *
	 * @param string $fallback
	 * @return bool
	 */
	private function isUsableFallback( string $fallback ): bool {
		if ( $fallback === '' ) {
			return false;
		}
		return !( str_starts_with( $fallback, ':' ) && str_ends_with( $fallback, ':' ) );
	}

	/**
	 * @param DOMElement $node
	 * @param string $name
	 * @return string
	 */
	private function getAltText( DOMElement $node, string $name ): string {
		$label = $node->getAttribute( 'ac:emoji-shortname' );
		if ( $label === '' ) {
			$label = $name;
		}
		$label = trim( $label, ':' );

		return str_replace( '_', ' ', $label );
	}

	/**
	 * @param DOMElement $node
	 * @param string $replacement
	 * @return void
	 */
	protected function replaceEmoticon( DOMElement $node, string $replacement ): void {
		if ( !empty( $replacement ) ) {
			$node->parentNode->replaceChild(
				$this->createTextNode( $node->ownerDocument, $replacement, __METHOD__ ),
				$node
			);
		}
	}

}
