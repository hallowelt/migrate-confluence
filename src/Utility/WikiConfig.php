<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class WikiConfig {

	/** @var WorkspaceDB */
	private WorkspaceDB $workspaceDB;

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB ) {
		$this->workspaceDB = $workspaceDB;
	}

	/**
	 * @return string
	 */
	public function getWikiNameForSpaceKey( string $spaceKey ): string {
		$name = $this->workspaceDB->getWikiConfigWikiNameForSpaceKey( $spaceKey );
		if ( $name === null ) {
			return strtolower( "wiki-$spaceKey" );
		}
		return $name;
	}

	/**
	 * @return string
	 */
	public function getNamespaceForSpaceKey( string $spaceKey ): string {
		$namespace = $this->workspaceDB->getWikiConfigNamespaceForSpaceKey( $spaceKey );
		if ( $namespace === null ) {
			return $spaceKey;
		}
		return $namespace;
	}

	/**
	 * @return string
	 */
	public function getRootPageForSpaceKey( string $spaceKey ): string {
		$root = $this->workspaceDB->getWikiConfigRootPageForSpaceKey( $spaceKey );
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
		$prefix = $this->getNamespaceForSpaceKey( $spaceKey );
		if ( empty( $prefix ) ) {
			$prefix = $this->getWikiNameForSpaceKey( $spaceKey );
		}
		if ( empty( $prefix ) ) {
			$prefix = $spaceKey;
		}

		return strtolower( "wiki-$prefix" );
	}
}
