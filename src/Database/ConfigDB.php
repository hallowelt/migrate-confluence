<?php

namespace HalloWelt\MigrateConfluence\Database;

use SQLite3;

class ConfigDB {

	/** @var SQLite3 */
	private SQLite3 $db;

	/**
	 * @param string $name
	 */
	public function __construct( string $name ) {
		$this->db = new SQLite3( $name );

		$this->createTable();
	}

	/**
	 * @return void
	 */
	private function createTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS config (
				mainpage TEXT,
				include_history INT,
				ext_ns_file_repo_compat INT,
				space_prefix BLOB,
				categories BLOB,
				analyzer_include_space_key BLOB,
				composer_include_namespace BLOB,
				composer_skip_titles LONGBLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	public function populateConfigTables( array $config ): void {
		$mainpage = isset( $config['mainpage'] ) ? $config['mainpage'] : 'Main page';
		$spacePrefix = isset( $config['space-prefix'] ) ? $config['space-prefix'] : [];

		$analyzerIncludeSpacekey = isset( $config['analyzer-include-spacekey'] )
			? $config['analyzer-include-spacekey']
			: [];
		$normalizedAnalyzerIncludeSpacekey = [];
		if ( !empty( $analyzerIncludeSpacekey ) ) {
			foreach ( $analyzerIncludeSpacekey as $key ) {
				$normalizedAnalyzerIncludeSpacekey[] = strtolower( $key );
			}
		}

		$composerIncludeNamespace = isset( $config['composer-include-namespace'] )
			? $config['composer-include-namespace']
			: [];
		$composerSkipTitles = isset( $config['composer-skip-titles'] )
			? $config['composer-skip-titles']
			: [];
		$categories = isset( $config['categories'] ) ? $config['categories'] : [];
		$extNsFileRepoCompat = isset( $config['ext-ns-file-repo-compat'] )
			? ( $config['ext-ns-file-repo-compat'] ? 1 : 0 )
			: 0;
		$includeHistory = isset( $config['include-history'] )
			? ( $config['include-history'] ? 1 : 0 )
			: 0;

		$transaction = $this->db->prepare(
			'INSERT INTO config (
				mainpage,
				space_prefix,
				analyzer_include_space_key,
				composer_include_namespace,
				composer_skip_titles,
				categories,
				ext_ns_file_repo_compat,
				include_history
			) VALUES (
				:mainpage,
				:space_prefix,
				:analyzer_include_space_key,
				:composer_include_namespace,
				:composer_skip_titles,
				:categories,
				:ext_ns_file_repo_compat,
				:include_history
			)'
		);

		$transaction->bindValue( ':mainpage', $mainpage, SQLITE3_TEXT );
		$transaction->bindValue( ':space_prefix', json_encode( $spacePrefix ), SQLITE3_TEXT );
		$transaction->bindValue(
			':analyzer_include_space_key',
			json_encode( $normalizedAnalyzerIncludeSpacekey ),
			SQLITE3_TEXT
		);
		$transaction->bindValue(
			':composer_include_namespace',
			json_encode( $composerIncludeNamespace ),
			SQLITE3_TEXT
		);
		$transaction->bindValue(
			':composer_skip_titles',
			json_encode( $composerSkipTitles ),
			SQLITE3_TEXT
		);
		$transaction->bindValue( ':categories', json_encode( $categories ), SQLITE3_TEXT );
		$transaction->bindValue( ':ext_ns_file_repo_compat', $extNsFileRepoCompat, SQLITE3_INTEGER );
		$transaction->bindValue( ':include_history', $includeHistory, SQLITE3_INTEGER );

		$transaction->execute();
	}

	/**
	 * @return string
	 */
	public function getMainPageName(): string {
		$transaction = $this->db->prepare(
			'SELECT (mainpage) FROM config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray();

		return $data[0];
	}

	/**
	 * @return array
	 */
	public function getSpaceKeyToPrefixMap(): array {
		$transaction = $this->db->prepare(
			'SELECT (space_prefix) FROM config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray( SQLITE3_ASSOC );

		if ( !isset( $data['space_prefix'] ) ) {
			return [];
		}

		return json_decode( $data['space_prefix'], true );
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
	 * @return array|null
	 */
	public function getConfigIncludeSpaceKeys(): ?array {
		$transaction = $this->db->prepare(
			'SELECT analyzer_include_space_key from config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray( SQLITE3_ASSOC );

		if ( !isset( $data['analyzer_include_space_key'] ) ) {
			return [];
		}

		return json_decode( $data['analyzer_include_space_key'], true );
	}

	/**
	 * @return array|null
	 */
	public function getConfigCategories(): ?array {
		$transaction = $this->db->prepare(
			'SELECT categories FROM config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray( SQLITE3_ASSOC );

		if ( !isset( $data['categories'] ) ) {
			return [];
		}

		return json_decode( $data['categories'], true );
	}

	/**
	 * @return bool
	 */
	public function getExtNsFileRepoCompat(): bool {
		$transaction = $this->db->prepare(
			'SELECT ext_ns_file_repo_compat FROM config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray( SQLITE3_ASSOC );

		if ( !isset( $data['ext_ns_file_repo_compat'] ) ) {
			return false;
		}

		if ( $data['ext_ns_file_repo_compat'] === 0 ) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	public function getIncludeHistory(): bool {
		$transaction = $this->db->prepare(
			'SELECT include_history FROM config'
		);

		$result = $transaction->execute();
		$data = $result->fetchArray( SQLITE3_ASSOC );

		if ( !isset( $data['include_history'] ) ) {
			return false;
		}

		if ( $data['include_history'] === 0 ) {
			return false;
		}

		return true;
	}
}