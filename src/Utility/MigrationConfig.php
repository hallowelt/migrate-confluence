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
	private function get( string $key, mixed $default ): mixed {
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
	public function getCategories(): array {
		return $this->get( 'categories', [] );
	}

	/**
	 * @return bool
	 */
	public function getExtNsFileRepoCompat(): bool {
		return $this->get( 'ext-ns-file-repo-compat', false );
	}

	/**
	 * @return bool
	 */
	public function getIncludeHistory(): bool {
		return $this->get( 'include-history', false );
	}

	/**
	 * @return int
	 */
	public function getComposerPagePerXmlLimit(): int {
		return $this->get( 'composer-page-per-xml-limit', 0 );
	}

	/**
	 * @return array
	 */
	public function getComposerSkipNamespaces(): array {
		return $this->get( 'composer-skip-namespace', [] );
	}

	/**
	 * @return array
	 */
	public function getComposerSkipTitles(): array {
		return $this->get( 'composer-skip-titles', [] );
	}

	public function getNsTalkPrefix(): string {
		return $this->get( 'ns-talk-prefix', 'Talk' );
	}
}
