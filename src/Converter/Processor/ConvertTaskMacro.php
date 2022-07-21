<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Converter\IProcessor;

/**
 *
 */
class ConvertTaskMacro implements IProcessor {
	/**
	 * @inheritDoc
	 */
	public function process( DOMDocument $dom ): void {
		$this->processNestedTaskLists( $dom );
		$this->processRootTaskLists( $dom );
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processRootTaskLists( DOMDocument $dom ) {
		$macroNodes = $dom->getElementsByTagName( 'task-list' );

		$macroNodeList = [];
		foreach ( $macroNodes as $macroNode ) {
			$macroNodeList[] = $macroNode;
		}

		foreach ( $macroNodeList as $macroNode ) {
			$replacement = $this->processTaskList( $macroNode );
			$macroNode->parentNode->replaceChild(
				$macroNode->ownerDocument->createTextNode(
					$replacement
				),
				$macroNode
			);
		}
	}

	/**
	 * @param DOMDocument $dom
	 * @return void
	 */
	private function processNestedTaskLists( DOMDocument $dom ) {
		$macroNodes = $dom->getElementsByTagName( 'task-list' );

		$macroNodeList = [];
		foreach ( $macroNodes as $macroNode ) {
			$macroNodeList[] = $macroNode;
		}

		foreach ( $macroNodeList as $macroNode ) {
			$replacement = $this->processTaskList( $macroNode );
		}
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function processTaskList( DOMElement $node ) {
		$taskNodes = [];
		foreach( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName !== 'ac:task' ) {
				continue;
			}
			$taskNodes[] = $childNode;
		}

		foreach( $taskNodes as $taskNode ) {
			$replacement = $this->processTask( $taskNode );
			$taskNode->parentNode->replaceChild(
				$taskNode->ownerDocument->createTextNode(
					$replacement
				),
				$taskNode
			);
		}

		return  '{{TaskListStart}}###BREAK###' .$node->nodeValue . '{{TaskListEnd}}###BREAK###';
	}

	/**
	 * @param DOMElement $taskNode
	 * @return string
	 */
	private function processTask( DOMElement $taskNode ): string {
		if ( !$taskNode->hasChildNodes() ) {
			return '';
		}

		$id = -1;
		$status = '';
		$body = '';
		foreach ( $taskNode->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:task-id' ) {
				$id = $childNode->nodeValue;
			}
			if ( $childNode->nodeName === 'ac:task-status' ) {
				$status = $childNode->nodeValue;
			}
			if ( $childNode->nodeName === 'ac:task-body' ) {
				$body =$this->getTaskBody( $childNode );
			}
		}

		$replacement = $this->makeTaskTemplate( $id, $status, $body );

		return $replacement;
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function getTaskBody( DOMElement $node ){
		$tasklistNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:task-list' ) {
				$tasklistNodes[] = $childNode;
			}
		}

		foreach ( $tasklistNodes as $tasklistNode ) {
			$tasklistReplacement = $this->processTaskList( $tasklistNode );
			$tasklistNode->parentNode->replaceChild(
				$tasklistNode->ownerDocument->createTextNode(
					$tasklistReplacement
				),
				$tasklistNode
			);
		}

		$wikiText = '';
		$wikiText .= $node->nodeValue;

		return $wikiText;
	}

	/**
	 * @param string $id
	 * @param string $status
	 * @param string $body
	 * @return string
	 */
	private function makeTaskTemplate( string $id, string $status, string $body ): string {
		return <<<HERE
{{Task###BREAK###
| id = $id###BREAK###
| status = $status###BREAK###
| body = $body###BREAK###
}}###BREAK###
HERE;
	}


}
