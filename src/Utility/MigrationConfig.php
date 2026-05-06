<?php

namespace HalloWelt\MigrateConfluence\Utility;

class MigrationConfig {

	/**
	 * @param array $config
	 */
	public function __construct( private array $config ) {
		$this->config = $config;
	}

	/**
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	private function get( string $key, mixed $default = null ): mixed {
		if ( !isset( $this->config[$key] ) ) {
			return $default;
		}
		return $this->config[$key];
	}

	/**
	 * @return string
	 */
	public function getMainPageName(): string {
		return $this->get( 'mainpage', 'Main Page' );
	}

	/**
	 * @return array
	 */
	public function getSpaceKeyToPrefixMap(): array {
		return $this->get( 'space-prefix', [] );
	}

	/**
	 * @param string $spaceKey
	 * @return string|null
	 */
	public function getPrefixFromSpaceKeyToPrefixMap( string $spaceKey ): ?string {
		$data = $this->getSpaceKeyToPrefixMap();

		if ( !isset( $data[$spaceKey] ) ) {
			return null;
		}

		return $data[$spaceKey];
	}

	/**
	 * @return array
	 */
	public function getConfigIncludeSpaceKeys(): array {
		return $this->get( 'analyzer-include-spacekey', [] );
	}

	/**
	 * @return array
	 */
	public function getComposerIncludeNamespace(): array {
		return $this->get( 'composer-include-namespace', [] );
	}

	/**
	 * @return array
	 */
	public function getComposerSkipTitles(): array {
		return $this->get( 'composer-skip-titles', [] );
	}

	/**
	 * @return array
	 */
	public function getConfigCategories(): array {
		return $this->get( 'categories', [] );
	}

	/**
	 * @return boolean
	 */
	public function getExtNsFileRepoCompat(): bool {
		return $this->get( 'ext-ns-file-repo-compat', false );
	}

	/**
	 * @return boolean
	 */
	public function getIncludeHistory(): bool {
		return $this->get( 'include-history', false );
	}

}
