<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 *
 */
class ConvertTaskListMacro implements IProcessor {
	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$this->processTaskBody( $dom );
		$this->processTask( $dom );
		$this->processTaskList( $dom );
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

		foreach( $node->childNodes as $childNode ) {
			$newNode = $childNode->cloneNode( true );
			$macroReplacement->appendChild( $newNode );
		}
		
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	private function processTask( DOMDocument $dom ) {
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

		foreach( $node->childNodes as $childNode ) {
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
			}
		}

		$txt = $macroReplacement->ownerDocument->createTextNode( "\n[x] " );
		if ( $macroReplacement->getAttribute( 'data-task-status' ) === 'incomplete') {
			$txt = $macroReplacement->ownerDocument->createTextNode( "\n[] " );
		}

		$macroReplacement->prepend( $txt );
		
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}

	/**
	 * @param DOMNode $node
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
		$macroReplacement->setAttribute( 'class', 'ac-tasklist' );

		foreach( $node->childNodes as $childNode ) {
			foreach ( $childNode->childNodes as $taskNode ) {
				$newNode = $taskNode->cloneNode( true );
				$macroReplacement->appendChild( $newNode );
			}
			
		}
		
		$node->parentNode->replaceChild( $macroReplacement, $node );
	}
}
