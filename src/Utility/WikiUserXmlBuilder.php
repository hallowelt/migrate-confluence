<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMDocument;
use DOMElement;

class WikiUserXmlBuilder {

	/** @var DOMDocument */
	private DOMDocument $dom;

	/** @var array */
	private array $users = [];

	public function __construct() {
		$this->init();
	}

	/**
	 * @return void
	 */
	public function reset(): void {
		$this->users = [];
		$this->init();
	}

	/**
	 * @param string $wikiUsername
	 * @param array $properties
	 * @return void
	 */
	public function addUser( string $wikiUsername, array $properties = [] ): void {
		if ( !isset( $this->users[$wikiUsername] ) ) {
			$this->users[$wikiUsername] = $properties;
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function buildAndSave( string $path ): bool {
		$this->build();
		$status = $this->dom->save( $path );
		return $status !== false;
	}

	/**
	 * @return void
	 */
	private function init(): void {
		$this->dom = new DOMDocument();
		$this->dom->formatOutput = true;
		$this->dom->loadXML( '<mediawiki></mediawiki>' );
	}

	/**
	 * @return void
	 */
	private function build(): void {
		$mediaWikiEl = $this->dom->getElementsByTagName( 'mediawiki' )->item( 0 );

		foreach ( $this->users as $wikiUsername => $properties ) {
			$userEl = $this->dom->createElement( 'user' );

			$titleEl = $this->dom->createElement( 'username', $wikiUsername );
			$userEl->append( $titleEl );

			foreach ( $properties as $name => $value ) {
				$this->appendUserData( $name, $value, $userEl );
			}

			$mediaWikiEl->append( $userEl );
		}
	}

	/**
	 * @param string $name
	 * @param mixed $value
	 * @param DOMElement $propertyEl
	 * @return void
	 */
	private function appendUserData( string $name, mixed $value, DOMElement $propertyEl ): void {
		if ( $name === 'name' || $name === 'key' ) {
			$name = "confluence-$name";
		}
		$dataEl = $this->dom->createElement( $name, $value );
		$propertyEl->append( $dataEl );
	}
}
