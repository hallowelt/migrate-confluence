<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\DataReader\AbstractDataReader;

class WikisConfig extends AbstractDataReader {

	/**
	 * @return string|null
	 */
	public function getWikiNameForSpaceKey( string $spaceKey ): ?string {
		$name = $this->db->getWikisConfigWikiNameForSpaceKey( $spaceKey );
		return $name;
	}

	/**
	 * @return string
	 */
	public function getNamespaceForSpaceKey( string $spaceKey ): string {
		$namespace = $this->db->getWikisConfigNamespaceForSpaceKey( $spaceKey );
		if ( $namespace === null ) {
			return $spaceKey;
		}
		return rtrim( $namespace, ':' );
	}

	/**
	 * @return string
	 */
	public function getRootPageForSpaceKey( string $spaceKey ): string {
		$root = $this->db->getWikisConfigRootPageForSpaceKey( $spaceKey );
		if ( $root === null ) {
			return '';
		}
		return $root;
	}

	/**
	 * @param string $spaceKey
	 * @return string
	 */
	public function getInterwikiPrefixForSpaceKey( string $spaceKey ): string {
		$prefix = $this->getWikiNameForSpaceKey( $spaceKey );
		if ( $prefix === null ) {
			return strtolower( "wiki-$spaceKey" );
		}
		return strtolower( "wiki-$prefix" );
	}
}
