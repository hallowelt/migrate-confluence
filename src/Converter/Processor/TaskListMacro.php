<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 *
 */
class TaskListMacro implements IProcessor {
	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$this->processTask( $dom );
		$this->processTaskList( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processTask( DOMDocument $dom ) {
		$this->processTaskBody( $dom );

		$elements = $dom->getElementsByTagName( 'task' );

		$items = [];
		foreach ( $elements as $element ) {
			$items[] = $element;
		}

		$items = array_reverse( $items );

		foreach ( $items as $item ) {
			$this->doProcessTask( $item );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessTask( DOMNode $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'task-replacement' );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:task-id' ) {
				$macroReplacement->setAttribute( 'data-task-id', $childNode->nodeValue );
				continue;
			}
			if ( $childNode->nodeName === 'ac:task-status' ) {
				$macroReplacement->setAttribute( 'data-task-status', $childNode->nodeValue );
				continue;
			}
			if ( $childNode->nodeName === 'task-body-replacement' ) {
				foreach ( $childNode->childNodes as $taskNode ) {
					$newNode = $taskNode->cloneNode( true );
					$macroReplacement->appendChild( $newNode );
				}
				continue;
			}
		}

		if ( $node instanceof DOMElement ) {
			$txt = $macroReplacement->ownerDocument->createTextNode( "\n[x] " );
			if ( $macroReplacement->getAttribute( 'data-task-status' ) === 'incomplete' ) {
				$txt = $macroReplacement->ownerDocument->createTextNode( "\n[] " );
			}
			$macroReplacement->prepend( $txt );
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processTaskBody( DOMDocument $dom ) {
		$elements = $dom->getElementsByTagName( 'task-body' );

		$items = [];
		foreach ( $elements as $element ) {
			$items[] = $element;
		}

		$items = array_reverse( $items );

		foreach ( $items as $item ) {
			$this->doProcessTaskBody( $item );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessTaskBody( DOMNode $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'task-body-replacement' );

		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode instanceof DOMElement ) {
				if ( $childNode->getAttribute( 'class' ) === 'placeholder-inline-tasks' ) {
					foreach ( $childNode->childNodes as $inlineChild ) {
						$newNode = $inlineChild->cloneNode( true );
						$macroReplacement->appendChild( $newNode );
					}
					continue;
				}
			}
			$newNode = $childNode->cloneNode( true );
			$macroReplacement->appendChild( $newNode );
		}

		if ( $node instanceof DOMElement ) {
			$ol = $node->getElementsByTagName( 'ol' );
			$ul = $node->getElementsByTagName( 'ul' );
			$div = $node->getElementsByTagName( 'div' );

			if ( count( $ol ) > 0 || count( $ul ) > 0 || count( $div ) > 0 ) {
				$brokenNode = $node->ownerDocument->createTextNode(
					'[[Categroy:Broken_macro/task]]'
				);
				$macroReplacement->appendChild( $brokenNode );
			}
		}
		$brokenNode = $node->ownerDocument->createElement( 'br' );
		$macroReplacement->appendChild( $brokenNode );

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processTaskList( DOMDocument $dom ) {
		$elements = $dom->getElementsByTagName( 'task-list' );

		$items = [];
		foreach ( $elements as $element ) {
			$items[] = $element;
		}

		$items = array_reverse( $items );

		foreach ( $items as $item ) {
			$this->doProcessTaskList( $item );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessTaskList( DOMNode $node ): void {
		$macroReplacement = $node->ownerDocument->createElement( 'div' );
		$macroReplacement->setAttribute( 'class', 'ac-task-list' );

		$broken = false;
		foreach ( $node->childNodes as $childNode ) {
			foreach ( $childNode->childNodes as $taskNode ) {
				if ( $childNode->nodeName !== 'task-replacement' ) {
					$broken = true;
					continue;
				}
				$newNode = $taskNode->cloneNode( true );
				$macroReplacement->appendChild( $newNode );
			}
		}

		if ( $broken === true ) {
			$brokenNode = $node->ownerDocument->createTextNode(
				'[[Categroy:Broken_macro/task-list]]'
			);
			$macroReplacement->appendChild( $brokenNode );
		}

		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
