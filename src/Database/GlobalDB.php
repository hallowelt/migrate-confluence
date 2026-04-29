<?php

namespace HalloWelt\MigrateConfluence\Database;

class GlobalDB extends MigrateConfluenceDB {
	
	/**
	 * @inheritDoc
	 */
	protected function createTables(): void {
		$this->createConfigTables();
		$this->createSpacesTable();
		$this->createSpaceDescriptionsToBodyContentsTable();
		$this->createSpaceDescriptionsToLabellingTable();
	}

	/**
	 * @return void
	 */
	private function createConfigTables(): void {
		$this->createConfigSpaceKeyToPrefixTable();
	}

	/**
	 * @return void
	 */
	private function createConfigSpaceKeyToPrefixTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS config_spacekey_to_prefix (
				space_key TEXT,
				prefix TEXT
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createSpacesTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS spaces (
				space_id INTEGER PRIMARY KEY,
				space_key TEXT,
				space_name TEXT,
				space_prefix TEXT,
				space_full_prefix TEXT,
				space_homepage_id INTEGER,
				space_description_id INTEGER
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createSpaceDescriptionsToBodyContentsTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS space_description_id_to_body_content_id (
				space_description_id INTEGER PRIMARY KEY,
				body_content_id INTEGER
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createSpaceDescriptionsToLabellingTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS space_description_id_to_labelling_id (
				space_description_id INTEGER PRIMARY KEY,
				space_id INTEGER
			);'
		);
	}

	/**
	 * @param string $spaceKey
	 * @param string $prefix
	 * @return void
	 */
	public function configMapSpaceKeyToPrefix( string $spaceKey, string $prefix ) {
		$this->db->exec(
			"INSERT INTO config_spacekey_to_prefix (space_key, prefix) VALUES ('" . $spaceKey ."', '" . $prefix . "')"
		);
	}

	/**
	 * @param string $spaceKey
	 * @param string $prefix
	 * @return void
	 */
	public function addToSpacesTable(
		int $spaceId, string $spaceKey, string $name, string $prefix, string $fullPrefix,
		int $homepageId, int $descriptionId
	) {
		$this->db->exec(
			"INSERT INTO spaces
			(space_id, space_key, space_name, space_prefix, space_full_prefix, 
			space_homepage_id, space_description_id)
			VALUES
			(" . $spaceId . ", '" . $spaceKey ."', '" . $name . "', '" . $prefix . "', '" . $fullPrefix . "', "
			 . $homepageId . ", " . $descriptionId . ")"
		);
	}

	/**
	 * @param string $spaceKey
	 * @param string $prefix
	 * @return void
	 */
	public function mapSpacedescriptionIdToBodyContentId(
		int $descriptionId, int $bodyContentId
	) {
		$this->db->exec(
			"INSERT INTO space_description_id_to_body_content_id
			(space_description_id, space_id)
			VALUES
			('" . $descriptionId . "', '" . $bodyContentId . "')"
		);
	}

	/**
	 * @param string $spaceKey
	 * @param string $prefix
	 * @return void
	 */
	public function mapSpacedescriptionIdToLabellingId(
		int $descriptionId, int $bodyContentId
	) {
		$this->db->exec(
			"INSERT INTO space_description_id_to_labelling_id
			(space_description_id, labelling_id)
			VALUES
			('" . $descriptionId . "', '" . $bodyContentId . "')"
		);
	}

	/**
	 * @param string $spaceKey
	 * @return string|null
	 */
	public function getSpacePrefixFromKey( string $spaceKey ): ?string {
		$transaction = $this->db->prepare(
			'SELECT prefix FROM config_spacekey_to_prefix WHERE space_key=:space_key'
		);
		$transaction->bindValue( 'space_key', $spaceKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		$data = $result->fetchArray();

		if ( !isset( $data['prefix'] ) ) {
			return null;
		}
		return $data['prefix'];
	}

	/**
	 * @param string $spaceKey
	 * @return array|null
	 */
	public function getSpacesTable( string|int $spaceId ): ?array {
		$transaction = $this->db->prepare(
			'SELECT * FROM spaces'
		);

		if ( is_string( $spaceId ) ) {
			$transaction->bindValue( 'space_id', $spaceId, SQLITE3_TEXT );
		} else {
			$transaction->bindValue( 'space_id', $spaceId, SQLITE3_INTEGER );
		}

		$result = $transaction->execute();
		$data = $result->fetchArray();

		return $data;
	}
}