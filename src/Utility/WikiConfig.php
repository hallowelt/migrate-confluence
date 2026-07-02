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
	public function getWikiConfigWikiNameForSpaceKey( string $spaceKey ): string {
		$name = $this->workspaceDB->getWikiConfigWikiNameForSpaceKey( $spaceKey );
		if ( $name === null ) {
			return strtolower( "wiki-$spaceKey" );
		}
		return $name;
	}

	/**
	 * @return string
	 */
	public function getWikiConfigNamespaceForSpaceKey( string $spaceKey ): string {
		$namespace = $this->workspaceDB->getWikiConfigNamespaceForSpaceKey( $spaceKey );
		if ( $namespace === null ) {
			return $spaceKey;
		}
		return $namespace;
	}

	/**
	 * @return string
	 */
	public function getWikiConfigRootPageForSpaceKey( string $spaceKey ): string {
		$root = $this->workspaceDB->getWikiConfigRootPageForSpaceKey( $spaceKey );
		if ( $root === null ) {
			return '';
		}
		return $root;
	}

	/**
	 * @return string
	 */
	public function getWikiConfigWikiNameForSpaceId( int $spaceId ): string {
		$name = $this->workspaceDB->getWikiConfigWikiNameForSpaceId( $spaceId );
		if ( $name === null ) {
			return strtolower( "wiki-$spaceId" );
		}
		return $name;
	}

	/**
	 * @return string
	 */
	public function getWikiConfigNamespaceForSpaceId( int $spaceId ): string {
		$namespace = $this->workspaceDB->getWikiConfigNamespaceForSpaceId( $spaceId );
		if ( $namespace === null ) {
			return (string)$spaceId;
		}
		return $namespace;
	}

	/**
	 * @return string
	 */
	public function getWikiConfigRootPageForSpaceId( int $spaceId ): string {
		$root = $this->workspaceDB->getWikiConfigRootPageForSpaceId( $spaceId );
		if ( $root === null ) {
			return '';
		}
		return $root;
	}
}