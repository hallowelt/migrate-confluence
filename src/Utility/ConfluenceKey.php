<?php

namespace HalloWelt\MigrateConfluence\Utility;

class ConfluenceKey {

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @return string
	 */
	public function newPageKeyFromSpaceId(
		?string $spaceId, string $confluenceTitle
	): string {
		$space = $this->getSpaceStringFromSpaceId( $spaceId );
		return $this->getPageKey( $space, $confluenceTitle );
	}

	/**
	 * @param string|null $spaceKey
	 * @param string $confluenceTitle
	 * @return string
	 */
	public function newPageKeyFromSpaceKey(
		?string $spaceKey, string $confluenceTitle
	): string {
		$space = $this->getSpaceStringFromSpaceKey( $spaceKey );
		return $this->getPageKey( $space, $confluenceTitle );
	}

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	public function newFileKeyFromSpaceId(
		?string $spaceId, string $confluenceTitle, string $origFilename
	): string {
		$space = $this->getSpaceStringFromSpaceId( $spaceId );
		return $this->getFileKey( $space, $confluenceTitle, $origFilename );
	}

	/**
	 * @param string|null $spaceKey
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	public function newFileKeyFromSpaceKey(
		string $spaceKey, string $confluenceTitle, string $origFilename
	): string {
		$space = $this->getSpaceStringFromSpaceKey( $spaceKey );
		return $this->getFileKey( $space, $confluenceTitle, $origFilename );
	}

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @param string $origFilename
	 * @return string
	 */
	private function getFileKey( string $spaceKey, string $confluenceTitle, string $origFilename ): string {
		return str_replace( ' ', '_', "Confluence_file---$spaceKey---$confluenceTitle---$origFilename" );
	}

	/**
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @return string
	 */
	private function getPageKey( string $spaceKey, string $confluenceTitle ): string {
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
