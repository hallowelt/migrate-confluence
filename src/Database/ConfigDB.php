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
		$fields = [];
		$values = [];

		$fields[] = 'mainpage';
		$value = isset( $config['mainpage'] )? $config['mainpage']:'Main page';
		$values[] = "'$value'";

		$fields[] = 'space_prefix';
		$value = isset( $config['space-prefix'] )? $config['space-prefix']:[];
		$values[] = "'" . json_encode( $value ) . "'";

		$fields[] = 'analyzer_include_space_key';
		$value = isset( $config['analyzer-include-spacekey'] )? $config['analyzer-include-spacekey']:[];
		$normalizedAnalyzerIncludeSpacekey = [];
		if ( !empty( $value ) ) {
			foreach ( $value as $key ) {
				$normalizedAnalyzerIncludeSpacekey[] = strtolower( $key );
			}
		}
		$values[] = "'" . json_encode( $normalizedAnalyzerIncludeSpacekey ) . "'";

		$fields[] = 'composer_include_namespace';
		$value = isset( $config['composer-include-namespace'] )? $config['composer-include-namespace']:[];
		$values[] = "'" . json_encode( $value ) . "'";

		$fields[] = 'composer_skip_titles';
		$value = isset( $config['composer-skip-titles'] )? $config['composer-skip-titles']:[];
		$values[] = "'" . json_encode( $value ) . "'";

		$fields[] = 'categories';
		$value = isset( $config['categories'] )? $config['categories']:[];
		$values[] = "'" . json_encode( $value ) . "'";

		$fields[] = 'ext_ns_file_repo_compat';
		$value = isset( $config['ext-ns-file-repo-compat'] )? $config['ext-ns-file-repo-compat']:false;
		$values[] = ( $value )? 1:0;

		$fields[] = 'include_history';
		$value = isset( $config['include-history'] )? $config['include-history']:false;
		$values[] = ( $value )? 1:0;

		$sql = "INSERT INTO config (" . implode( ',', $fields ). ") VALUES (" . implode( ',', $values ). ")";

		$this->db->exec( $sql );
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