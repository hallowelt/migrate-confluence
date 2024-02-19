<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMText;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class PreserveStructuredMacroTasksReport extends StructuredMacroProcessorBase {

	/** @var ConversionDataLookup */
	protected $dataLookup;

	/**
	 * @param ConversionDataLookup $dataLookup
	 */
	public function __construct( ConversionDataLookup $dataLookup ) {
		$this->dataLookup = $dataLookup;
	}

	/**
	 *
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'tasks-report-macro';
	}

	/**
	 * @param DOMNode $node
	 * @return void
	 */
	protected function doProcessMacro( $node ): void {
		$paramNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramNodes[] = $childNode;
			}
		}

		$taskreport = $node->ownerDocument->createElement( 'div' );
		$taskreport->setAttribute( 'class', 'PRESERVETASKSREPORT' );

		foreach ( $paramNodes as $paramNode ) {
			if ( !$paramNode->hasAttributes() ) {
				continue;
			}

			$name = $paramNode->getAttribute( 'ac:name' );
			if ( $name === 'spaces' ) {
				$namespaces = '';
				foreach ( $paramNode->childNodes as $childNode ) {
					if ( $childNode instanceof DOMText === false ) {
						continue;
					}
					$namespaces = $this->findNamespaceName( $childNode->nodeValue );
				}
				$taskreport->setAttribute( 'namespaces', $namespaces );
				continue;
			}

			if ( $name === 'assignees' ) {
				$users = [];
				foreach ( $paramNode->childNodes as $childNode ) {
					$user = $this->findUserName( $childNode );
					if ( $user !== '' ) {
						$users[] = $user;
					}
				}
				$taskreport->setAttribute( 'user', implode( '|', $users ) );
				continue;
			}

			$status = 'unchecked';
			if ( $name === 'status' ) {
				$status = [];

				foreach ( $paramNode->childNodes as $childNode ) {
					if ( $childNode->nodeValue === 'complete' ) {
						$state = 'checked';
					} else {
						$state = 'unchecked';
					}
					$status[] = $state;
				}
			}
			if ( is_array( $status ) ) {
				$status = implode( '|', $status );
			}
			$taskreport->setAttribute( 'status', $status );
		}

		$node->parentNode->replaceChild( $taskreport, $node );
	}

	/**
	 * @param string $spaceKeys
	 * @return string
	 */
	private function findNamespaceName( string $spaceKeys ): string {
		return str_replace( ',', '|', $spaceKeys );
	}

	/**
	 * @param DOMElement $user
	 * @return string
	 */
	private function findUserName( DOMElement $user ): string {
		if ( $user->nodeName !== 'ri:user' ) {
			return '';
		}
		$key = $user->getAttribute( 'ri:userkey' );
		$username = $this->dataLookup->getUsernameFromUserKey( $key );
		return $username;
	}
}
