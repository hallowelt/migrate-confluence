<?php

namespace HalloWelt\MigrateConfluence\Utility;

use DOMDocument;
use DOMException;
use DOMNode;

class ConversionHelper {

	/**
	 * @param string $macroName
	 * @return string
	 */
	public function getCategoryBrokenMacro( string $macroName ): string {
		return $this->getCategoryBroken( "macro/$macroName" );
	}

	/**
	 * @param string $name
	 * @return string
	 */
	public function getCategoryBroken( string $name ): string {
		$name = str_replace( ' ', '_', $name );
		return "[[Category:Broken_$name]]";
	}

	/**
	 * @param DOMDocument $dom
	 * @param string $text
	 * @param string $caller
	 * @return DOMNode
	 *
	 * @throws DOMException
	 */
	protected function createTextNode(
		DOMDocument $dom, string $text, string $caller
	): DOMNode {
		if ( $dom instanceof DOMDocument === false ) {
			new DOMException(
				"Trying to createTextNode on invalid DOMDocument in " . $caller
			);
		}
		$textNode = $dom->createTextNode( $text );
		if ( $textNode instanceof DOMNode === false ) {
			new DOMException(
				"createTextNode does not return DOMNode in " . $caller
			);
		}
		return $textNode;
	}

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @return string
	 */
	protected function getConfluencePageKeyFromSpaceId(
		?string $spaceId, string $confluenceTitle
	): string {
		$space = $this->getSpaceStringFromSpaceId( $spaceId );
		return $this->getConfluencePageKey( $space, $confluenceTitle );
	}

	/**
	 * @param string|null $spaceKey
	 * @param string $confluenceTitle
	 * @return string
	 */
	protected function getConfluencePageKeyFromSpaceKey(
		?string $spaceKey, string $confluenceTitle
	): string {
		$space = $this->getSpaceStringFromSpaceKey( $spaceKey );
		return $this->getConfluencePageKey( $space, $confluenceTitle );
	}

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	protected function getConfluenceFileKeyFromSpaceId(
		?string $spaceId, string $confluenceTitle, string $origFilename
	): string {
		$space = $this->getSpaceStringFromSpaceId( $spaceId );
		return $this->getConfluenceFileKey( $space, $confluenceTitle, $origFilename );
	}

	/**
	 * @param string|null $spaceKey
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	protected function getConfluenceFileKeyFromSpaceKey(
		string $spaceKey, string $confluenceTitle, string $origFilename
	): string {
		$space = $this->getSpaceStringFromSpaceKey( $spaceKey );
		return $this->getConfluenceFileKey( $space, $confluenceTitle, $origFilename );
	}

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	private function getConfluenceFileKey( string $spaceKey, string $confluenceTitle, string $origFilename ): string {
		return str_replace( ' ', '_', "Confluence_file---$spaceKey---$confluenceTitle---$origFilename" );
	}

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @return string
	 */
	private function getConfluencePageKey( string $spaceKey, string $confluenceTitle ): string {
		return str_replace( ' ', '_', "Confluence_page---$spaceKey---$confluenceTitle" );
	}

	/**
	 * @param int|null $spaceId
	 * @return string
	 */
	private function getSpaceStringFromSpaceId( ?int $spaceId ): string {
		$space = '';
		if ( !empty( $spaceId ) ) {
			$space = (string)$spaceId;
		}
		return $space;
	}

	/**
	 * @param string|null $spaceKey
	 * @return string
	 */
	private function getSpaceStringFromSpaceKey( ?string $spaceKey ): string {
		$space = '';
		if ( !empty( $spaceKey ) ) {
			$space = $spaceKey;
		}
		return $space;
	}
}
