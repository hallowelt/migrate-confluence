<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMElement;
use DOMText;
use HalloWelt\MigrateConfluence\Converter\IUsesPlaceholder;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class TasksReportMacro extends StructuredMacroProcessorBase implements IUsesPlaceholder {

	/**
	 * @param DBConversionDataLookup $dataLookup
	 */
	public function __construct(
		protected readonly DBConversionDataLookup $dataLookup,
		private readonly PlaceholderManager $placeholderManager
	) {
	}

	/**
	 * @return string
	 */
	protected function getMacroName(): string {
		return 'tasks-report-macro';
	}

	/**
	 * @inheritDoc
	 */
	protected function doProcessMacro( DOMElement $node ): void {
		$paramNodes = [];
		foreach ( $node->childNodes as $childNode ) {
			if ( $childNode->nodeName === 'ac:parameter' ) {
				$paramNodes[] = $childNode;
			}
		}

		$taskreport = $node->ownerDocument->createElement( 'taskreport' );
		$taskreport->setAttribute( 'status', 'unchecked' );

		foreach ( $paramNodes as $paramNode ) {
			if ( $paramNode instanceof DOMElement === false ) {
				continue;
			}
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
				$taskreport->setAttribute( 'status', implode( '|', $status ) );
			}
		}

		$taskreport = $node->ownerDocument->createTextNode(
			$this->placeholderManager->getPlaceholder(
			$taskreport->ownerDocument->saveXML( $taskreport ) ) );
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
		$username = $this->dataLookup->getUsernameFromUserKey( $key ) ?? $key;
		return $username;
	}
}
