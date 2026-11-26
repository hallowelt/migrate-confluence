<?php

namespace HalloWelt\MigrateConfluence\Utility;

use InvalidArgumentException;

class MigrationConfig {

	/**
	 * @param array $config
	 */
	public function __construct( private readonly array $config ) {
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

	/**
	 * @return string
	 */
	public function getNsTalkPrefix(): string {
		return $this->get( 'ns-talk-prefix', 'Talk' );
	}

	/**
	 * How the page hierarchy is carried over: 'page-hierarchies', 'enhanced-sidebar' or 'none'.
	 * Galaxy has native page hierarchies; the enhanced sidebar is the fallback for other profiles.
	 *
	 * @return string
	 * @throws InvalidArgumentException If the configured value is unknown
	 */
	public function getHierarchyMethod(): string {
		$method = $this->get(
			'hierarchy-method',
			$this->getProfile() === 'bluespice-galaxy' ? 'page-hierarchies' : 'enhanced-sidebar'
		);
		if ( !in_array( $method, [ 'page-hierarchies', 'enhanced-sidebar', 'none' ], true ) ) {
			throw new InvalidArgumentException(
				"Invalid value '$method' for 'hierarchy-method'. "
				. "Allowed: page-hierarchies, enhanced-sidebar, none."
			);
		}
		return $method;
	}

	/**
	 * @return bool
	 */
	public function getAddUserinfo(): bool {
		return $this->get( 'add-userinfo', false );
	}

	/**
	 * @return string
	 */
	public function getProfile(): string {
		return strtolower( $this->get( 'profile', 'bluespice-galaxy' ) );
	}

	/**
	 * @return string
	 */
	public function getCSVDelimiter(): string {
		return $this->get( 'csv-delimiter', ',' );
	}

	/**
	 * @return ?string
	 */
	public function getHookHandler(): ?string {
		return $this->get( 'hook-handler', null );
	}

	/**
	 * @return string Empty if not configured
	 */
	public function getSourceTimezone(): string {
		return (string)$this->get( 'source-timezone', '' );
	}
}
