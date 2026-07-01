<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMDocument;
use DOMElement;
use DOMException;

class WikiFileXmlBuilder {

	/** @var DOMDocument */
	private DOMDocument $dom;

	/** @var array */
	private array $files = [];

	public function __construct() {
		$this->init();
	}

	/**
	 * @return void
	 */
	public function reset(): void {
		$this->files = [];
		$this->init();
	}

	/**
	 * @param string $fileTitle
	 * @param string $path
	 * @param string $timestamp
	 * @param string $contributor
	 * @param string $comment
	 * @return void
	 */
	public function addFileRevision(
		string $fileTitle, string $path, string $timestamp = '',
		string $contributor = '', string $comment = ''
	): void {
		if ( !isset( $this->files[$fileTitle] ) ) {
			$this->files[$fileTitle] = [];
		}

		if ( $timestamp !== '' ) {
			$data['timestamp'] = $timestamp;
		}

		if ( $contributor !== '' ) {
			$data['contributor'] = $contributor;
		}

		if ( $comment !== '' ) {
			$data['comment'] = $comment;
		}

		$data['data'] = $path;

		$this->files[$fileTitle][] = $data;
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

		foreach ( $this->files as $fileTitle => $revisions ) {
			$fileEl = $this->dom->createElement( 'file' );

			$titleEl = $this->dom->createElement( 'title', $fileTitle );
			$fileEl->append( $titleEl );

			foreach ( $revisions as $revisionData ) {
				$revisionEl = $this->dom->createElement( 'revision' );

				$this->appendRevisionData( $revisionData, $revisionEl );

				$fileEl->append( $revisionEl );
			}

			$mediaWikiEl->append( $fileEl );
		}
	}

	/**
	 * @param array $revisionData
	 * @param DOMElement $revisionEl
	 * @return void
	 */
	private function appendRevisionData( array $revisionData, DOMElement $revisionEl ): void {
		foreach ( $revisionData as $name => $value ) {
			if ( $name === 'contributor' ) {
				$this->appendRevisionDataUserItem( $value, $revisionEl );
			} else {
				$this->appendRevisionDataItem( $name, $value, $revisionEl );
			}
		}
	}

	/**
	 * @param string $value
	 * @param DOMElement $revisionEl
	 *
	 * @return void
	 * @throws DOMException
	 */
	private function appendRevisionDataUserItem( string $value, DOMElement $revisionEl ): void {
		$dataEl = $this->dom->createElement( 'contributor' );
		$userEl = $this->dom->createElement( 'username', $value );

		$dataEl->append( $userEl );
		$revisionEl->append( $dataEl );
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @param DOMElement $revisionEl
	 *
	 * @return void
	 * @throws DOMException
	 */
	private function appendRevisionDataItem( string $name, string $value, DOMElement $revisionEl ): void {
		$dataEl = $this->dom->createElement( $name, $value );
		$revisionEl->append( $dataEl );
	}
}
