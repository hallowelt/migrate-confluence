<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class WikisConfig {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB ) {
		$this->workspaceDB = $workspaceDB;
	}

	/**
	 * @return string|null
	 */
	public function getWikiNameForSpaceKey( string $spaceKey ): ?string {
		$name = $this->workspaceDB->getWikisConfigWikiNameForSpaceKey( $spaceKey );
		return $name;
	}

	/**
	 * @return string
	 */
	public function getNamespaceForSpaceKey( string $spaceKey ): string {
		$namespace = $this->workspaceDB->getWikisConfigNamespaceForSpaceKey( $spaceKey );
		if ( $namespace === null ) {
			return $spaceKey;
		}
		return rtrim( $namespace, ':' );
	}

	/**
	 * @return string
	 */
	public function getRootPageForSpaceKey( string $spaceKey ): string {
		$root = $this->workspaceDB->getWikisConfigRootPageForSpaceKey( $spaceKey );
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
			return '';
		}
		return strtolower( "wiki-$prefix" );
	}
}
