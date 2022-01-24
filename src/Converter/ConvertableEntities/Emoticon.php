<?php

namespace HalloWelt\MigrateConfluence\Converter\ConvertableEntities;

use HalloWelt\MigrateConfluence\Converter\IProcessable;

class Emoticon implements IProcessable {

	protected $aEmoticonMapping = [
		'smile' => ':)',
		'sad' => ':( ',
		'cheeky' => ':P',
		'laugh' => ':D',
		'wink' => ';)',
		'thumbs-up' => '(y)',
		'thumbs-down' => '(n)',
		'information' => '(i)',
		'tick' => '(/)',
		'cross' => '(x)',
		'warning' => '(!)',

		// Non standard!?
		'question' => '(?)',
	];

	/**
	 *
	 * @param ConfluenceConverter $sender
	 * @param DOMElement $match
	 * @param DOMDocument $dom
	 * @param DOMXPath $xpath
	 * @return void
	 */
	public function process( $sender, $match, $dom, $xpath ): void {
		$replacement = '';
		$sKey = $match->getAttribute( 'ac:name' );

		if ( $sKey === 'blue-star' ) {
			$fallbackAttr = $match->getAttribute( 'ac:emoji-fallback' );
			$replacement = $fallbackAttr;
		} elseif ( !isset( $this->aEmoticonMapping[$sKey] ) ) {
			$replacement = '[[Category:Broken_emoticon]]';
		} else {
			$replacement = $this->aEmoticonMapping[$sKey];
		}
		if ( !empty( $replacement ) ) {
			$match->parentNode->replaceChild(
				$dom->createTextNode( $replacement ),
				$match
			);
		}
	}
}
