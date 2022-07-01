<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

class Emoticon implements IProcessor {

	/**
	 * @var array
	 */
	protected $emoticonMapping = [
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
	 * @param DOMNode $node
	 * @return string
	 */
	private function getReplacement( $node ): string {
		$name = $node->getAttribute( 'ac:name' );

		$replacement = '';
		if ( $name === 'blue-star' ) {
			$replacement = $node->getAttribute( 'ac:emoji-fallback' );
		}
		elseif ( !isset( $this->emoticonMapping[ $name ] ) ) {
			$replacement = '[[Category:Broken_emoticon]]';
		} else {
			$replacement = $this->emoticonMapping[ $name ];
		}

		return $replacement;
	}

	/**
	 * @param DOMNode $node
	 * @param string $replacement
	 * @return void
	 */
	protected function replaceEmoticon( DOMNode $node, string $replacement ): void {
		if ( !empty( $replacement ) ) {
			$node->parentNode->replaceChild(
				$node->ownerDocument->createTextNode( $replacement ),
				$node
			);
		}
	}

}
