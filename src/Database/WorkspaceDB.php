<?php

namespace HalloWelt\MigrateConfluence\Database;

use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

class WorkspaceDB {

	/** @var SQLite3 */
	private SQLite3 $db;

	/** @var array Cached prepared statements keyed by SQL string */
	private array $stmtCache = [];

	/**
	 * @param string $name
	 */
	public function __construct( string $name ) {
		$this->db = new SQLite3( $name );
		$this->db->enableExceptions( true );

		$this->db->exec( 'PRAGMA journal_mode = WAL' );
		$this->db->exec( 'PRAGMA synchronous = NORMAL' );
		$this->db->busyTimeout( 5000 );

		$this->createTables();
	}

	/**
	 * @return void
	 */
	public function beginTransaction(): void {
		$this->db->exec( 'BEGIN TRANSACTION' );
	}

	/**
	 * @return void
	 */
	public function commitTransaction(): void {
		$this->db->exec( 'COMMIT' );
	}

	/**
	 * Prepare a statement, caching it for reuse across repeated calls with the same SQL.
	 *
	 * @param string $sql
	 * @return SQLite3Stmt
	 */
	private function cachedPrepare( string $sql ): SQLite3Stmt {
		if ( !isset( $this->stmtCache[$sql] ) ) {
			$this->stmtCache[$sql] = $this->db->prepare( $sql );
		} else {
			try {
				$this->stmtCache[$sql]->reset();
			} catch ( \Exception $e ) {
				// Statement was in an error state from a previously failed execution; re-prepare it.
				$this->stmtCache[$sql] = $this->db->prepare( $sql );
			}
		}
		return $this->stmtCache[$sql];
	}

	/**
	 * @param string $indexName
	 * @param string $tableName
	 * @param string $columnName
	 * @return void
	 */
	private function doCreateIndex( string $indexName, string $tableName, string $columnName ): void {
		$this->db->exec(
			'CREATE INDEX IF NOT EXISTS ' . $indexName
			. ' ON ' . $tableName . ' (' . $columnName . ')'
		);
	}

	/**
	 * @param string $table
	 * @return array
	 */
	private function getAllData( string $table ): array {
		$allowedTables = [
			'logging',
			'spaces',
			'spaces_descriptions',
			'pages',
			'pages_meta',
			'page_templates',
			'page_template_contents',
			'blog_posts',
			'blog_posts_meta',
			'body_contents',
			'attachments',
			'attachments_meta',
			'page_attachments',
			'additional_attachments',
			'users',
			'content_properties',
			'comments',
			'labellings',
			'labels',
			'gliffy',
		];

		if ( !in_array( $table, $allowedTables, true ) ) {
			throw new \InvalidArgumentException( 'Table not allowed: ' . $table );
		}

		$transaction = $this->db->prepare(
			'SELECT * FROM ' . $table
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param SQLite3Result $result
	 * @return array
	 */
	private function fetchDbArray( SQLite3Result $result ): array {
		$data = [];
		$res = $result->fetchArray( SQLITE3_ASSOC );
		while ( $res ) {
			$data[] = $res;
			$res = $result->fetchArray( SQLITE3_ASSOC );
		}
		return $data;
	}

	/**
	 * Execute an add statement and return whether it succeeded.
	 *
	 * @param SQLite3Stmt $transaction
	 * @return bool
	 */
	private function executeTransactionWithStatus( SQLite3Stmt $transaction ): bool {
		try {
			$result = $transaction->execute();
		} catch ( \Exception $e ) {
			return false;
		}

		$result->finalize();
		return true;
	}

	/**
	 * @return void
	 */
	private function createTables(): void {
		// General logging
		$this->createTableLogging();

		// Tables to collect invalid titles or BodyContents ( e. g. content length)
		$this->createTableInvalidTitles();
		$this->createTableInvalidBodyContents();
		$this->createTableInvalidAttachmentTitles();

		// Object tables
		$this->createTableSpaces();
		$this->createTableSpaceDescriptions();
		$this->createTablePages();
		$this->createTableBlogPosts();
		$this->createTableBodyContents();
		$this->createTableBodyContentBodies();
		$this->createTableAttachments();
		$this->createTablePageAttachments();
		$this->createTableAdditionalAttachments();
		$this->createTableUsers();
		$this->createTableContentProperties();
		$this->createTableComments();
		$this->createTableLabellings();
		$this->createTableLabels();
		$this->createTableGliffy();
		$this->createTablePagesMeta();
		$this->createTableBlogPostsMeta();
		$this->createTableAttachmentsMeta();
		$this->createTablePageTemplate()
		$this->createTablePageTemplateContents();

		$this->createIndexes();
	}

			/**
	 * @return void
	 */
	private function createIndexes(): void {
		$this->doCreateIndex( 'idx_body_contents_content_id', 'body_contents', 'content_id' );
		$this->doCreateIndex( 'idx_attachments_container_id', 'attachments', 'container_id' );
		$this->doCreateIndex( 'idx_page_attachments_target', 'page_attachments', 'target_attachment_filename' );
		$this->doCreateIndex( 'idx_additional_attachments_target', 'additional_attachments', 'target_attachment_filename' );
		$this->doCreateIndex( 'idx_pages_space_id', 'pages', 'space_id' );
		$this->doCreateIndex( 'idx_pages_space_title', 'pages', 'space_id, confluence_title' );
		$this->doCreateIndex( 'idx_blog_posts_space_id', 'blog_posts', 'space_id' );
		$this->doCreateIndex( 'idx_page_templates_template_id', 'page_templates', 'template_id' );
	}

	/**
	 * @return void
	 */
	private function createTableLogging(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS logging (
				type CHAR,
				step CHAR,
				caller CHAR,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS invalid_titles (
				page_id INT PRIMARY KEY,
				wiki_title CHAR,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidBodyContents(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS invalid_body_contents (
				body_content_id INT PRIMARY KEY,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidAttachmentTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS invalid_attachment_titles (
				attachment_id INT PRIMARY KEY,
				wiki_title CHAR,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableSpaces(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS spaces (
				space_id INT PRIMARY KEY,
				space_key CHAR,
				space_name CHAR,
				space_prefix CHAR,
				homepage_id INT,
				description_id INT
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableSpaceDescriptions(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS spaces_descriptions (
				space_description_id INT PRIMARY KEY,
				revision_timestamp CHAR,
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				body_content_ids BLOB,
				labelling_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTablePages(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS pages (
				page_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				revision_timestamp CHAR,
				last_modifier CHAR,
				content_status CHAR,
				original_version_id INT,
				version CHAR,
				parent_page_id INT,		
				body_content_ids BLOB,
				historical_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableBlogPosts(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_posts (
				page_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				revision_timestamp CHAR,
				last_modifier CHAR,
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				body_content_ids BLOB,
				historical_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableBodyContents(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS body_contents (
				body_content_id INT PRIMARY KEY,
				content_id INT,
				class CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableBodyContentBodies(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS body_content_bodies (
				body_content_id INT PRIMARY KEY,
				body CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableAttachments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachments (
				attachment_id INT PRIMARY KEY,
				space_id INT,
				filename CHAR,
				file_extension CHAR,
				container_id INT,
				content_status CHAR,
				version CHAR,
				revision_timestamp CHAR,
				last_modifier CHAR,
				original_version_id INT,
				attachment_reference CHAR,
				historical_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTablePageAttachments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_attachments (
				attachment_id INT,
				page_id INT,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableAdditionalAttachments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS additional_attachments (
				attachment_id INT,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	/**
	 * $attachment id is not set as primary key because there can be multiple entries for the same attachment id
	 * if the attachment is attached to multiple pages with different names.
	 * @return void
	 */
	private function createTableUsers(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS users (
				user_key CHAR PRIMARY KEY,
				wiki_user_name CHAR,
				email CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableContentProperties(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS content_properties (
				property_id INT PRIMARY KEY,
				property_name CHAR,
				content_class CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableComments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS comments (
				comment_id INT PRIMARY KEY,
				container_id INT,
				content_class CHAR,
				content_status CHAR,
				user_key CHAR,
				body_content_ids BLOB,
				created CHAR,
				modified CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableLabellings(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS labellings (
				labelling_id INT PRIMARY KEY,
				label_id INT,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableLabels(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS labels (
				label_id INT PRIMARY KEY,
				name CHAR,
				namespace CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableGliffy(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS gliffy (
				space_id INT,
				confluence_title CHAR,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTablePagesMeta(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS pages_meta (
				page_id INT PRIMARY KEY,
				meta BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableBlogPostsMeta(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_posts_meta (
				page_id INT PRIMARY KEY,
				meta BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableAttachmentsMeta(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachments_meta (
				attachment_id INT PRIMARY KEY,
				meta BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTablePageTemplate(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_templates (
				template_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				revision_timestamp CHAR,
				last_modifier CHAR,
				content_status CHAR,
				original_version_id INT,
				version CHAR,
				parent_page_id INT,		
				body_content_ids BLOB,
				historical_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTablePageTemplateContents(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_template_contents (
				template_id INT PRIMARY KEY,
				space_id INT,
				name CHAR,
				content TEXT
			);'
		);
	}

	/**
	 * @param string $type
	 * @param string $step
	 * @param string $caller
	 * @param string $text
	 * @return void
	 */
	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$transaction = $this->cachedPrepare(
			'INSERT INTO logging (
				type,
				step,
				caller,
				text
			) VALUES (
				:type,
				:step,
				:caller,
				:text
			)'
		);

		$transaction->bindValue( ':type', $type, SQLITE3_TEXT );
		$transaction->bindValue( ':step', $step, SQLITE3_TEXT );
		$transaction->bindValue( ':caller', $caller, SQLITE3_TEXT );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @param string $step
	 * @param string $type
	 * @return array
	 */
	public function getLogEntriesForStep( string $step, string $type = '' ): array {
		if ( $type !== '' ) {
			$transaction = $this->cachedPrepare(
				'SELECT caller,text FROM logging WHERE step = :step AND type = :type'
			);
			$transaction->bindValue( ':step', $step, SQLITE3_TEXT );
			$transaction->bindValue( ':type', $type, SQLITE3_TEXT );
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT type,caller,text FROM logging WHERE step = :step'
			);
			$transaction->bindValue( ':step', $step, SQLITE3_TEXT );
		}

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @param string $text
	 * @return void
	 */
	public function addInvalidTitle( int $pageId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO invalid_titles (
				page_id,
				wiki_title,
				text
			) VALUES (
				:page_id,
				:wiki_title,
				:text
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getInvalidTitles(): array {
		return $this->getAllData( 'invalid_titles' );
	}

	/**
	 * @param int $bodyContentId
	 * @param string $text
	 * @return void
	 */
	public function addInvalidBodyContent( int $bodyContentId, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO invalid_body_contents (
				body_content_id,
				text
			) VALUES (
				:body_content_id,
				:text
			)'
		);

		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getInvalidBodyContents(): array {
		return $this->getAllData( 'invalid_body_contents' );
	}

	/**
	 * @param int $attachmentId
	 * @param string $wikiTitle
	 * @param string $text
	 * @return void
	 */
	public function addInvalidAttachmentTitle( int $attachmentId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO invalid_attachment_titles (
				attachment_id,
				wiki_title,
				text
			) VALUES (
				:attachment_id,
				:wiki_title,
				:text
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getInvalidAttachmentTitles(): array {
		return $this->getAllData( 'invalid_attachment_titles' );
	}

	/**
	 * @param int $spaceId
	 * @param string $spaceKey
	 * @param string $spaceName
	 * @param string $prefix
	 * @param int $homepageId
	 * @param int $descriptionId
	 * @return bool True on success, false on error.
	 */
	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO spaces (
				space_id,
				space_key,
				space_name,
				space_prefix,
				homepage_id,
				description_id
			) VALUES (
				:space_id,
				:space_key,
				:space_name,
				:space_prefix,
				:homepage_id,
				:description_id
			)'
		);

		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );
		$transaction->bindValue( ':space_name', $spaceName, SQLITE3_TEXT );
		$transaction->bindValue( ':space_prefix', $prefix, SQLITE3_TEXT );
		$transaction->bindValue( ':homepage_id', $homepageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':description_id', $descriptionId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getSpaces(): array {
		return $this->getAllData( 'spaces' );
	}

	public function getSpaceHomepageIdForSpaceId( int $spaceId ): int {
		$transaction = $this->cachedPrepare(
			'SELECT homepage_id FROM spaces WHERE space_id = :space_id LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return -1;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['homepage_id'] ) ) {
			return -1;
		}

		return (int)$data['homepage_id'];
	}

	/**
	 * @param int $descriptionId
	 * @return int
	 */
	public function getSpaceIdForDescriptionId( int $descriptionId ): int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM spaces WHERE description_id = :description_id LIMIT 1'
		);
		$transaction->bindValue( ':description_id', $descriptionId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return -1;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return -1;
		}

		return (int)$data['space_id'];
	}

	/**
	 * @param string $spaceKey
	 * @return int
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM spaces WHERE space_key = :space_key LIMIT 1'
		);
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return -1;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return -1;
		}

		return (int)$data['space_id'];
	}

	/**
	 * Get the mediawiki namespace for a given space key.
	 * If space key is not found, return the space key as namespace as default.
	 *
	 * @param string $spaceKey
	 * @return string
	 */
	public function getSpacePrefixFromSpaceKey( string $spaceKey ): string {
		$transaction = $this->cachedPrepare(
			'SELECT space_prefix FROM spaces WHERE space_key = :space_key LIMIT 1'
		);
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return "$spaceKey:";
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_prefix'] ) ) {
			return "$spaceKey:";
		}

		return $data['space_prefix'];
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array {
		$transaction = $this->cachedPrepare(
			'SELECT space_id,space_prefix FROM spaces'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['space_id'];
			$value = $item['space_prefix'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToKey(): array {
		$transaction = $this->cachedPrepare(
			'SELECT space_id,space_key FROM spaces'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['space_id'];
			$value = $item['space_key'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @return array
	 */
	public function getMapSpaceIdToHomepageId(): array {
		$transaction = $this->cachedPrepare(
			'SELECT space_id,homepage_id FROM spaces'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['space_id'];
			$value = $item['homepage_id'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @param int $spaceDescriptionId
	 * @param string $revisionTimestamp
	 * @param string $contentStatus
	 * @param string $version
	 * @param int $originalVersionId
	 * @param array $bodyContentIds
	 * @param array $lagellingIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addSpaceDescription(
		int $spaceDescriptionId, string $revisionTimestamp, string $contentStatus, string $version,
		int $originalVersionId, array $bodyContentIds, array $lagellingIds, array $properties, array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$labellingIdsJson = json_encode( $lagellingIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT INTO spaces_descriptions (
				space_description_id,
				revision_timestamp,
				content_status,
				version,
				original_version_id,
				body_content_ids,
				labelling_ids,
				properties,
				collection
			) VALUES (
				:space_description_id,
				:revision_timestamp,
				:content_status,
				:version,
				:original_version_id,
				:body_content_ids,
				:labelling_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':space_description_id', $spaceDescriptionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':labelling_ids', $labellingIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Update body_content_ids for a space description.
	 *
	 * @param int $spaceDescriptionId
	 * @param array $bodyContentIds
	 * @return bool True on success, false on error.
	 */
	public function updateSpaceDescriptionBodyContentIds( int $spaceDescriptionId, array $bodyContentIds ): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'UPDATE spaces_descriptions SET body_content_ids = :body_content_ids 
			WHERE space_description_id = :space_description_id'
		);

		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':space_description_id', $spaceDescriptionId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getSpaceDescriptions(): array {
		return $this->getAllData( 'spaces_descriptions' );
	}

	/**
	 * Get all revisions of the space description for a given space.
	 *
	 * Revisions are linked through `original_version_id` and sorted newest first.
	 *
	 * @param int $spaceId
	 * @return array
	 */
	public function getSpaceDescriptionRevisionsForSpaceId( int $spaceId ): array {
		$anchorTransaction = $this->cachedPrepare(
			'SELECT description_id FROM spaces WHERE space_id = :space_id LIMIT 1'
		);
		$anchorTransaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$anchorResult = $anchorTransaction->execute();
		if ( $anchorResult === false ) {
			return [];
		}

		$anchorData = $anchorResult->fetchArray( SQLITE3_ASSOC );
		$anchorResult->finalize();

		if ( $anchorData === false || !isset( $anchorData['description_id'] ) ) {
			return [];
		}

		$descriptionId = (int)$anchorData['description_id'];

		$transaction = $this->cachedPrepare(
			'SELECT * FROM spaces_descriptions
			WHERE space_description_id = :description_id
				OR original_version_id = :description_id
			ORDER BY revision_timestamp DESC'
		);
		$transaction->bindValue( ':description_id', $descriptionId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * Check if a space description with the given space description ID already exists in the database.
	 *
	 * @param int $spaceDescriptionId
	 * @return bool
	 */
	public function spaceDescriptionIdExists( int $spaceDescriptionId ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT space_description_id FROM spaces_descriptions
			WHERE space_description_id = :space_description_id LIMIT 1'
		);
		$transaction->bindValue( ':space_description_id', $spaceDescriptionId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return false;
		}

		$exists = $result->fetchArray( SQLITE3_ASSOC ) !== false;
		$result->finalize();

		return $exists;
	}

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param string $contentStatus
	 * @param string $version
	 * @param int $originalVersionId
	 * @param int $parentPageId
	 * @param array $bodyContentIds
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addPage(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $lastModifier,
		string $contentStatus,
		string $version,
		int $originalVersionId,
		int $parentPageId,
		array $bodyContentIds,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$historicalIdsJson = json_encode( $historicalIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT INTO pages (
				page_id,
				space_id,
				confluence_title,
				wiki_title,
				revision_timestamp,
				last_modifier,
				content_status,
				version,
				original_version_id,
				parent_page_id,
				body_content_ids,
				historical_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:revision_timestamp,
				:last_modifier,
				:content_status,
				:version,
				:original_version_id,
				:parent_page_id,
				:body_content_ids,
				:historical_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':parent_page_id', $parentPageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE pages SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 * @return bool True on success, false on error.
	 */
	public function updatePageSpaceId( int $pageId, int $spaceId ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE pages SET space_id = :space_id WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Update body_content_ids for a page.
	 *
	 * @param int $pageId
	 * @param array $bodyContentIds
	 * @return bool True on success, false on error.
	 */
	public function updatePageBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'UPDATE pages SET body_content_ids = :body_content_ids WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPages(): array {
		return $this->getAllData( 'pages' );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @return string
	 */
	public function getTargetPageTitleFromSpaceId( int $spaceId, string $confluenceTitle ): string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM pages WHERE space_id = :space_id AND confluence_title = :confluence_title LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return '';
		}

		return $data['wiki_title'];
	}

	/**
	 * Get the target page title for a given page ID.
	 * If the page has an original version, recursively look up the original version
	 * until the original version is reached and return its wiki title.
	 *
	 * @param int $pageId
	 * @return string
	 */
	public function getTargetPageTitleFromPageId( int $pageId ): string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false ) {
			return '';
		}

		if ( isset( $data['original_version_id'] ) && $data['original_version_id'] > 0 ) {
			$originalVersionId = (int)$data['original_version_id'];
			return $this->getTargetPageTitleFromPageId( $originalVersionId );
		}

		if ( !isset( $data['wiki_title'] ) ) {
			return '';
		}

		return $data['wiki_title'];
	}

	/**
	 * @return array
	 */
	public function getPageIdTargetPageTitleMap(): array {
		$transaction = $this->cachedPrepare(
			'SELECT page_id, wiki_title FROM pages WHERE content_status = "current"'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['page_id'];
			$value = $item['wiki_title'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getPageRevisionsForPageId( int $pageId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT revision_timestamp, version, body_content_ids FROM pages
			WHERE page_id = :page_id OR original_version_id = :page_id
			ORDER BY revision_timestamp DESC'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $pageId
	 * @return string
	 */
	public function getConfluencePageTitleFromPageId( int $pageId ): string {
		$transaction = $this->cachedPrepare(
			'SELECT confluence_title FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['confluence_title'] ) ) {
			return '';
		}

		return $data['confluence_title'];
	}

	/**
	 * Check if a page with the given page ID already exists in the database.
	 *
	 * @param int $pageId
	 * @return bool
	 */
	public function pageIdExists( int $pageId ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT page_id FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return false;
		}

		$exists = $result->fetchArray( SQLITE3_ASSOC ) !== false;
		$result->finalize();

		return $exists;
	}

	/**
	 * @param int $pageId
	 * @return int The space_id for the given page_id, or -1 if not found.
	 */
	public function getSpaceIdForPageId( int $pageId ): int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return -1;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return -1;
		}

		return (int)$data['space_id'];
	}

	/**
	 * @return array
	 */
	public function getMapPageIdtoParentPageId(): array {
		$transaction = $this->cachedPrepare(
			'SELECT page_id,parent_page_id FROM pages'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['page_id'];
			$value = $item['parent_page_id'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @return array
	 */
	public function getMapPagesTitles(): array {
		$transaction = $this->cachedPrepare(
			'SELECT space_id,confluence_title,wiki_title FROM pages'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$spaceId = $item['space_id'];
			$confluenceTitle = $item['confluence_title'];
			$key = "$spaceId---$confluenceTitle";
			$value = $item['wiki_title'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @return array
	 */
	public function getMapPageIdToConfluenceTitle(): array {
		$transaction = $this->cachedPrepare(
			'SELECT page_id,confluence_title FROM pages'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['page_id'];
			$value = $item['confluence_title'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param string $contentStatus
	 * @param string $version
	 * @param int $originalVersionId
	 * @param array $bodyContentIds
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addBlogPost(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $lastModifier,
		string $contentStatus,
		string $version,
		int $originalVersionId,
		array $bodyContentIds,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$historicalIdsJson = json_encode( $historicalIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT INTO blog_posts (
				page_id,
				space_id,
				confluence_title,
				wiki_title,
				revision_timestamp,
				last_modifier,
				content_status,
				version,
				original_version_id,
				body_content_ids,
				historical_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:revision_timestamp,
				:last_modifier,
				:content_status,
				:version,
				:original_version_id,
				:body_content_ids,
				:historical_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE blog_posts SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $pageId
	 * @param int $spaceId
	 * @return bool True on success, false on error.
	 */
	public function updateBlogPostSpaceId( int $pageId, int $spaceId ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE blog_posts SET space_id = :space_id WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Update body_content_ids for a blog post.
	 *
	 * @param int $pageId
	 * @param array $bodyContentIds
	 * @return bool True on success, false on error.
	 */
	public function updateBlogPostBodyContentIds( int $pageId, array $bodyContentIds ): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'UPDATE blog_posts SET body_content_ids = :body_content_ids WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getBlogPosts(): array {
		return $this->getAllData( 'blog_posts' );
	}

	/**
	 * @return array
	 */
	public function getBlogPostIdTargetBlogPostTitleMap(): array {
		$transaction = $this->cachedPrepare(
			'SELECT page_id, wiki_title FROM blog_posts WHERE content_status = "current"'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['page_id'];
			$value = $item['wiki_title'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @param int $blogPostId
	 * @return array
	 */
	public function getBlogPostRevisionsForPageId( int $blogPostId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT revision_timestamp, version, content_status, body_content_ids FROM blog_posts
			WHERE page_id = :page_id OR original_version_id = :page_id
			ORDER BY revision_timestamp DESC'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $blogPostId
	 * @return bool
	 */
	public function blogPostIdExists( int $blogPostId ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT page_id FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return false;
		}

		$exists = $result->fetchArray( SQLITE3_ASSOC ) !== false;
		$result->finalize();

		return $exists;
	}

	/**
	 * @param int $blogPostId
	 * @return int The space_id for the given blog post page_id, or -1 if not found.
	 */
	public function getSpaceIdForBlogPostId( int $blogPostId ): int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return -1;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return -1;
		}

		return (int)$data['space_id'];
	}

	/**
	 * Get the target wiki title for a given blog post ID.
	 *
	 * @param int $blogPostId
	 * @return string
	 */
	public function getTargetBlogPostTitleFromBlogPostId( int $blogPostId ): string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return '';
		}

		return $data['wiki_title'];
	}

	/**
	 * Get the target wiki title for a given blog post ID.
	 *
	 * @param int $blogPostId
	 * @return string
	 */
	public function getConfluenceBlogPostTitleFromBlogPostId( int $blogPostId ): string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return '';
		}

		return $data['wiki_title'];
	}

	/**
	 * @param int $bodyContentId
	 * @param int $contentId
	 * @param string $class
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addBodyContent(
		int $bodyContentId,
		int $contentId,
		string $class,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->cachedPrepare(
			'INSERT INTO body_contents (
				body_content_id,
				content_id,
				class,
				properties
			) VALUES (
				:body_content_id,
				:content_id,
				:class,
				:properties
			)'
		);

		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_id', $contentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':class', $class, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getBodyContents(): array {
		return $this->getAllData( 'body_contents' );
	}

	/**
	 * @param int $contentId
	 * @return array
	 */
	public function getBodyContentIdsForContentId( int $contentId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT body_content_id FROM body_contents WHERE content_id = :content_id'
		);
		$transaction->bindValue( ':content_id', $contentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$bodyContentIds = [];
		foreach ( $data as $item ) {
			if ( isset( $item['body_content_id'] ) ) {
				$bodyContentIds[] = $item['body_content_id'];
			}

		}

		return $bodyContentIds;
	}

	/**
	 * @param int $bodyContentId
	 * @return int
	 */
	public function getContentIdForBodyContentId( int $bodyContentId ): int {
		$transaction = $this->cachedPrepare(
			'SELECT content_id FROM body_contents WHERE body_content_id = :body_content_id'
		);
		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$contentId = -1;
		foreach ( $data as $item ) {
			if ( isset( $item['content_id'] ) ) {
				$contentId = $item['content_id'];
			}
		}

		// TODO: Add a error if there are more then one page_id's for this body_content_id

		return $contentId;
	}

	/**
	 * @param int $bodyContentId
	 * @param string $body
	 * @return bool True on success, false on error.
	 */
	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO body_content_bodies (
				body_content_id,
				body
			) VALUES (
				:body_content_id,
				:body
			)'
		);

		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body', $body, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getBodyContentBodies(): array {
		return $this->getAllData( 'body_content_bodies' );
	}

	/**
	 * @param int $bodyContentId
	 * @return array
	 */
	public function getBodyForBodyContentId( int $bodyContentId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT body FROM body_content_bodies WHERE body_content_id = :body_content_id'
		);
		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$bodies = [];
		foreach ( $data as $item ) {
			if ( isset( $item['body'] ) ) {
				$bodies[] = $item['body'];
			}

		}

		return $bodies;
	}

	/**
	 * @param int $bodyContentId
	 * @return string|null
	 */
	public function getBodyContentBodyByBodyContentId( int $bodyContentId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT body FROM body_content_bodies WHERE body_content_id = :body_content_id LIMIT 1'
		);
		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['body'] ) ) {
			return null;
		}

		return $data['body'];
	}

	/**
	 * @param int $attachmentId
	 * @param int $spaceId
	 * @param string $filename
	 * @param string $fileExtension
	 * @param int $containerContentId
	 * @param string $contentStatus
	 * @param string $version
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
	 * @param int $originalVersionId
	 * @param string $attachmentReference
	 * @param array $historicalIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addAttachment(
		int $attachmentId,
		int $spaceId,
		string $filename,
		string $fileExtension,
		int $containerContentId,
		string $contentStatus,
		string $version,
		string $revisionTimestamp,
		string $lastModifier,
		int $originalVersionId,
		string $attachmentReference,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$historicalIdsJson = json_encode( $historicalIds );

		$transaction = $this->cachedPrepare(
			'INSERT INTO attachments (
				attachment_id,
				space_id,
				filename,
				file_extension,
				container_id,
				content_status,
				version,
				revision_timestamp,
				last_modifier,
				original_version_id,
				attachment_reference,
				historical_ids,
				properties,
				collection
			) VALUES (
				:attachment_id,
				:space_id,
				:filename,
				:file_extension,
				:container_id,
				:content_status,
				:version,
				:revision_timestamp,
				:last_modifier,
				:original_version_id,
				:attachment_reference,
				:historical_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':filename', $filename, SQLITE3_TEXT );
		$transaction->bindValue( ':container_id', $containerContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':file_extension', $fileExtension, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':attachment_reference', $attachmentReference, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getAttachments(): array {
		return $this->getAllData( 'attachments' );
	}

	/**
	 * Get a single attachment by ID.
	 *
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachment( int $attachmentId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM attachments WHERE attachment_id = :attachment_id LIMIT 1'
		);
		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false ) {
			return [];
		}

		return $data;
	}

	/**
	 * Get all revisions of an attachment for a given attachment ID.
	 *
	 * Revisions are linked through `original_version_id` and sorted newest first.
	 *
	 * @param int $attachmentId
	 * @return array
	 */
	public function getAttachmentRevisionsForAttachmentId( int $attachmentId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM attachments
			WHERE attachment_id = :attachment_id
				OR original_version_id = :attachment_id
			ORDER BY revision_timestamp DESC'
		);
		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $attachmentId
	 * @param int $pageId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 * @return bool
	 */
	public function addPageAttachment(
		int $attachmentId,
		int $pageId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO page_attachments (
				attachment_id,
				page_id,
				original_attachment_filename,
				target_attachment_filename
			) VALUES (
				:attachment_id,
				:page_id,
				:original_attachment_filename,
				:target_attachment_filename
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );
		$transaction->bindValue( ':target_attachment_filename', $targetAttachmentFilename, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $attachmentId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 * @return bool
	 */
	public function addAdditionalAttachment(
		int $attachmentId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO additional_attachments (
				attachment_id,
				original_attachment_filename,
				target_attachment_filename
			) VALUES (
				:attachment_id,
				:original_attachment_filename,
				:target_attachment_filename
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );
		$transaction->bindValue( ':target_attachment_filename', $targetAttachmentFilename, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Get the wikit file title for a given space key, confluence title and original attachment filename.
	 * If no entry is found, return the original attachment filename as title
	 * and mark it as broken link (isBroken = true) in the returned array.
	 *
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return array
	 */
	public function getTargetFileTitleFromSpaceKey(
		string $spaceKey, string $confluenceTitle, string $originalAttachmentFilename
	): array {
		$transaction = $this->cachedPrepare(
			'SELECT pa.target_attachment_filename FROM page_attachments pa
			JOIN pages p ON pa.page_id = p.page_id
			WHERE p.space_key = :space_key AND p.confluence_title = :confluence_title
			AND pa.original_attachment_filename = :original_attachment_filename
			LIMIT 1'
		);
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [
				'title' => $originalAttachmentFilename,
				'isBroken' => true
			];
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['target_attachment_filename'] ) ) {
			return [
				'title' => $originalAttachmentFilename,
				'isBroken' => true
			];
		}

		return [
			'title' => $data['target_attachment_filename'],
			'isBroken' => false
		];
	}

	/**
	 * Get the wikit file title for a given space id, confluence title and original attachment filename.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string
	 */
	public function getTargetFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): string {
		$transaction = $this->cachedPrepare(
			'SELECT pa.target_attachment_filename FROM page_attachments pa
			JOIN pages p ON pa.page_id = p.page_id
			WHERE p.space_id = :space_id AND p.confluence_title = :confluence_title
			AND pa.original_attachment_filename = :original_attachment_filename
			LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['target_attachment_filename'] ) ) {
			return '';
		}

		return $data['target_attachment_filename'];
	}

	/**
	 * @param string $attachmentTargetFileTitle
	 * @return string
	 */
	public function getAttachmentReference( string $attachmentTargetFileTitle ): string {
		$transaction = $this->cachedPrepare(
			'SELECT a.attachment_reference FROM attachments a
			JOIN page_attachments pa ON pa.attachment_id = a.attachment_id
			WHERE pa.target_attachment_filename = :target_attachment_filename
			ORDER BY CASE WHEN lower( a.content_status ) = "current" THEN 0 ELSE 1 END, a.attachment_id DESC
			LIMIT 1'
		);
		$transaction->bindValue( ':target_attachment_filename', $attachmentTargetFileTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['attachment_reference'] ) ) {
			return '';
		}

		return (string)$data['attachment_reference'];
	}

	/**
	 * Returns all target file titles attached to a given page.
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string[]
	 */
	public function getTargetFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT pa.target_attachment_filename FROM attachments a
			JOIN pages p ON a.container_id = p.page_id
			JOIN page_attachments pa ON pa.attachment_id = a.attachment_id AND pa.page_id = p.page_id
			WHERE p.space_id = :space_id
				AND p.confluence_title = :confluence_title
				AND lower( a.content_status ) = "current"'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $rawPageTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		$fileTitles = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row ) {
			if ( isset( $row['target_attachment_filename'] ) && $row['target_attachment_filename'] !== '' ) {
				$fileTitles[] = $row['target_attachment_filename'];
			}
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}
		$result->finalize();

		return array_values( array_unique( $fileTitles ) );
	}

	/**
	 * Get all target file titles for a given space key and confluence title.
	 *
	 * @param string $spaceKey
	 * @param string $confluenceTitle
	 * @return array
	 */
	public function getAllTargetFileTitlesBySpaceKey( string $spaceKey, string $confluenceTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT pa.original_attachment_filename, pa.target_attachment_filename FROM page_attachments pa
			JOIN pages p ON pa.page_id = p.page_id
			WHERE p.space_key = :space_key AND p.confluence_title = :confluence_title'
		);
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		$fileTitles = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row ) {
			if ( isset( $row['original_attachment_filename'] ) && isset( $row['target_attachment_filename'] ) ) {
				$fileTitles[] = [
					'original' => $row['original_attachment_filename'],
					'target' => $row['target_attachment_filename']
				];
			}
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}
		$result->finalize();

		return $fileTitles;
	}

	/**
	 * Returns target file titles with their full metadata for all attachments on a page.
	 * The returned array is keyed by confluence file key. Each value contains 'targetTitle'
	 * plus any additional metadata fields (e.g. 'labels', 'mediaType', etc.).
	 *
	 * @param int $spaceId
	 * @param string $rawPageTitle
	 * @return string[] Map of confluenceFileKey => metadata (including 'targetTitle')
	 */
	public function getAttachmentMetadataForPage( int $spaceId, string $rawPageTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT pa.original_attachment_filename, pa.target_attachment_filename, am.meta FROM page_attachments pa
			JOIN pages p ON pa.page_id = p.page_id
			LEFT JOIN attachments_meta am ON pa.attachment_id = am.attachment_id
			WHERE p.space_id = :space_id AND p.confluence_title = :confluence_title'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $rawPageTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		$metadataMap = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row ) {
			if ( isset( $row['original_attachment_filename'] ) && isset( $row['target_attachment_filename'] ) ) {
				$confluenceFileKey = $row['original_attachment_filename'];
				$meta = json_decode( $row['meta'] ?? '[]', true );
				if ( !is_array( $meta ) ) {
					$meta = [];
				}

				$metadataMap[$confluenceFileKey] = array_merge(
					$meta,
					[ 'targetTitle' => $row['target_attachment_filename'] ]
				);
			}
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}
		$result->finalize();

		return $metadataMap;
	}

	/**
	 * @param string $wikiTitle
	 * @return bool
	 */
	public function checkPageAttachmentWikiTitleExists( string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT 1 FROM page_attachments WHERE target_attachment_filename = :wiki_title LIMIT 1'
		);
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result->fetchArray() !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $wikiTitle
	 * @return bool
	 */
	public function checkAdditionalAttachmentWikiTitleExists( string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT 1 FROM additional_attachments WHERE target_attachment_filename = :wiki_title LIMIT 1'
		);
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result->fetchArray() !== false ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function getPageAttachments(): array {
		return $this->getAllData( 'page_attachments' );
	}

	/**
	 * @return array
	 */
	public function getAdditionalAttachments(): array {
		return $this->getAllData( 'additional_attachments' );
	}

	/**
	 * @param int $pageId
	 * @return array
	 */
	public function getPageAttachmentsForPageId( int $pageId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM page_attachments WHERE page_id = :page_id'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * Return the wiki title of the first page linked to this attachment via attachments.container_id.
	 *
	 * @param int $attachmentId
	 * @return string
	 */
	public function getWikiTitleForAttachmentId( int $attachmentId ): string {
		$transaction = $this->cachedPrepare(
			'SELECT p.wiki_title FROM attachments a
			JOIN pages p ON p.page_id = a.container_id
			WHERE a.attachment_id = :attachment_id
			ORDER BY p.page_id ASC
			LIMIT 1'
		);
		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return '';
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return '';
		}

		return (string)$data['wiki_title'];
	}

	/**
	 * @param string $userKey
	 * @param string $wikiUsername
	 * @param string $email
	 * @param array $properties
	 * @return bool
	 */
	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO users (
				user_key,
				wiki_user_name,
				email,
				properties
			) VALUES (
				:user_key,
				:wiki_user_name,
				:email,
				:properties
			)'
		);

		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_user_name', $wikiUsername, SQLITE3_TEXT );
		$transaction->bindValue( ':email', $email, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getUsers(): array {
		return $this->getAllData( 'users' );
	}

	/**
	 * Get the mediawiki username for a given user key.
	 * If user key is not found, return the user key as username as default.
	 *
	 * @param string $userKey
	 * @return string
	 */
	public function getUsernameFromUserKey( string $userKey ): string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_user_name FROM users WHERE user_key = :user_key LIMIT 1'
		);
		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return $userKey;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_user_name'] ) ) {
			return $userKey;
		}

		return $data['wiki_user_name'];
	}

	/**
	 * @param int $propertyId
	 * @param string $propertyName
	 * @param string $class
	 * @param array $properties
	 * @return bool
	 */
	public function addContentProperty(
		int $propertyId,
		string $propertyName,
		string $class,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->cachedPrepare(
			'INSERT INTO content_properties (
				property_id,
				property_name,
				content_class,
				properties
			) VALUES (
				:property_id,
				:property_name,
				:content_class,
				:properties
			)'
		);

		$transaction->bindValue( ':property_id', $propertyId, SQLITE3_INTEGER );
		$transaction->bindValue( ':property_name', $propertyName, SQLITE3_TEXT );
		$transaction->bindValue( ':content_class', $class, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getContentProperties(): array {
		return $this->getAllData( 'content_properties' );
	}

	/**
	 * @param int $commentId
	 * @param int $containerContentId
	 * @param string $class
	 * @param string $contentStatus
	 * @param string $userKey
	 * @param array $bodyContentIds
	 * @param string $created
	 * @param string $modiefied
	 * @param array $properties
	 * @return bool
	 */
	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modiefied, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'INSERT INTO comments (
				comment_id,
				container_id,
				content_class,
				user_key,
				body_content_ids,
				content_status,
				created,
				modified,
				properties
			) VALUES (
				:comment_id,
				:container_id,
				:content_class,
				:user_key,
				:body_content_ids,
				:content_status,
				:created,
				:modified,
				:properties
			)'
		);

		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':container_id', $containerContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_class', $class, SQLITE3_TEXT );
		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':created', $created, SQLITE3_TEXT );
		$transaction->bindValue( ':modified', $modiefied, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Update body_content_ids for a comment.
	 *
	 * @param int $commentId
	 * @param array $bodyContentIds
	 * @return bool True on success, false on error.
	 */
	public function updateCommentBodyContentIds( int $commentId, array $bodyContentIds ): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'UPDATE comments SET body_content_ids = :body_content_ids WHERE comment_id = :comment_id'
		);

		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getComments(): array {
		return $this->getAllData( 'comments' );
	}

	/**
	 * Returns all page-level comments and the corresponding page wiki title.
	 *
	 * @return array
	 */
	public function getCommentsForPages(): array {
		$transaction = $this->cachedPrepare(
			'SELECT c.*, p.wiki_title FROM comments c
			LEFT JOIN pages p ON p.page_id = c.container_id
			WHERE c.content_class = :content_class'
		);
		$transaction->bindValue( ':content_class', 'Page', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $commentId
	 * @return bool
	 */
	public function commentIdExists( int $commentId ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT comment_id FROM comments WHERE comment_id = :comment_id LIMIT 1'
		);
		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return false;
		}

		$exists = $result->fetchArray( SQLITE3_ASSOC ) !== false;
		$result->finalize();

		return $exists;
	}

	/**
	 * @param int $labellingId
	 * @param int $labelId
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->cachedPrepare(
			'INSERT INTO labellings (
				labelling_id,
				label_id,
				properties
			) VALUES (
				:labelling_id,
				:label_id,
				:properties
			)'
		);

		$transaction->bindValue( ':labelling_id', $labellingId, SQLITE3_INTEGER );
		$transaction->bindValue( ':label_id', $labelId, SQLITE3_INTEGER );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getLabellings(): array {
		return $this->getAllData( 'labellings' );
	}

	/**
	 * @param int $labellingId
	 * @return array|null
	 */
	public function getLabellingById( int $labellingId ): ?array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM labellings WHERE labelling_id = :labelling_id LIMIT 1'
		);
		$transaction->bindValue( ':labelling_id', $labellingId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		if ( $data === [] ) {
			return null;
		}

		return $data[0];
	}

	/**
	 * @return array
	 */
	public function getMapLabellingIdToLabelId(): array {
		$transaction = $this->cachedPrepare(
			'SELECT labelling_id,label_id FROM labellings'
		);

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$key = $item['labelling_id'];
			$value = $item['label_id'];
			$map[$key] = $value;
		}

		return $map;
	}

	/**
	 * @param int $labelId
	 * @param string $name
	 * @param string $namespace
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->cachedPrepare(
			'INSERT INTO labels (
				label_id,
				name,
				namespace,
				properties
			) VALUES (
				:label_id,
				:name,
				:namespace,
				:properties
			)'
		);

		$transaction->bindValue( ':label_id', $labelId, SQLITE3_INTEGER );
		$transaction->bindValue( ':name', $name, SQLITE3_TEXT );
		$transaction->bindValue( ':namespace', $namespace, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getLabels(): array {
		return $this->getAllData( 'labels' );
	}

	/**
	 * @param int $labelId
	 * @return array|null
	 */
	public function getLabelById( int $labelId ): ?array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM labels WHERE label_id = :label_id LIMIT 1'
		);
		$transaction->bindValue( ':label_id', $labelId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		if ( $data === [] ) {
			return null;
		}

		return $data[0];
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 * @return bool
	 */
	public function addGliffy(
		int $spaceId,
		string $confluenceTitle,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO gliffy (
				space_id,
				confluence_title,
				original_attachment_filename,
				target_attachment_filename
			) VALUES (
				:space_id,
				:confluence_title,
				:original_attachment_filename,
				:target_attachment_filename
			)'
		);

		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );
		$transaction->bindValue( ':target_attachment_filename', $targetAttachmentFilename, SQLITE3_TEXT );

		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getGliffy(): array {
		return $this->getAllData( 'gliffy' );
	}

	/**
	 * @param int $pageId
	 * @param array $meta
	 * @return bool True on success, false on error.
	 */
	public function addPageMeta(
		int $pageId, array $meta
	): bool {
		$metaJson = json_encode( $meta );
		$transaction = $this->cachedPrepare(
			'INSERT INTO pages_meta (
				page_id,
				meta
			) VALUES (
				:page_id,
				:meta
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':meta', $metaJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPageMeta(): array {
		return $this->getAllData( 'pages_meta' );
	}

	/**
	 * @param int $pageId
	 * @param array $meta
	 * @return bool True on success, false on error.
	 */
	public function addBlogPostMeta(
		int $pageId, array $meta
	): bool {
		$metaJson = json_encode( $meta );
		$transaction = $this->cachedPrepare(
			'INSERT INTO blog_posts_meta (
				page_id,
				meta
			) VALUES (
				:page_id,
				:meta
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':meta', $metaJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getBlogPostMeta(): array {
		return $this->getAllData( 'blog_posts_meta' );
	}

	/**
	 * @param int $attachmentId
	 * @param array $meta
	 * @return bool True on success, false on error.
	 */
	public function addAttachmentMeta(
		int $attachmentId, array $meta
	): bool {
		$metaJson = json_encode( $meta );
		$transaction = $this->cachedPrepare(
			'INSERT INTO attachments_meta (
				attachment_id,
				meta
			) VALUES (
				:attachment_id,
				:meta
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':meta', $metaJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getAttachmentMeta(): array {
		return $this->getAllData( 'attachments_meta' );
	}

	public function addPageTemplate(
		int $templateId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $lastModifier,
		string $contentStatus,
		string $version,
		int $originalVersionId,
		int $parentPageId,
		array $bodyContentIds,
		array $historicalIds,
		array $properties,
		array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$historicalIdsJson = json_encode( $historicalIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT INTO pages (
				template_id,
				space_id,
				confluence_title,
				wiki_title,
				revision_timestamp,
				last_modifier,
				content_status,
				version,
				original_version_id,
				parent_page_id,
				body_content_ids,
				historical_ids,
				properties,
				collection
			) VALUES (
				:template_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:revision_timestamp,
				:last_modifier,
				:content_status,
				:version,
				:original_version_id,
				:parent_page_id,
				:body_content_ids,
				:historical_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':parent_page_id', $parentPageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPageTemplates(): array {
		return $this->getAllData( 'page_templates' );
	}

	/**
	 * @param int $templateId
	 * @return array|null
	 */
	public function getPageTemplateById( int $templateId ): ?array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM page_templates WHERE template_id = :template_id LIMIT 1'
		);
		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row === false ) {
			return null;
		}

		return $row;
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getTemplateNameFromTemplateId( int $templateId ): ?string {
		$template = $this->getPageTemplateById( $templateId );
		if ( $template === null ) {
			return null;
		}
		return $template['name'] ?? null;
	}

	/**
	 * @param int $templateId
	 * @return int|null
	 */
	public function getSpaceIdFromTemplateId( int $templateId ): ?int {
		$template = $this->getPageTemplateById( $templateId );
		if ( $template === null || $template['space_id'] === null ) {
			return null;
		}
		return (int)$template['space_id'];
	}

	/**
	 * @param int $templateId
	 * @return string|null
	 */
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		$template = $this->getPageTemplateById( $templateId );
		if ( $template === null || empty( $template['name'] ) ) {
			return null;
		}

		$spacePrefix = '';
		if ( $template['space_id'] !== null ) {
			$spaceId = (int)$template['space_id'];
			$spaces = $this->getMapSpaceIdToPrefix();
			if ( isset( $spaces[$spaceId] ) ) {
				$spacePrefix = $spaces[$spaceId];
			}
		}

		return 'Template:' . $spacePrefix . $template['name'];
	}

	/**
	 * @return array
	 */
	public function getPageTemplates(): array {
		return $this->getAllData( 'page_templates' );
	}

		/**
	 * @param int $templateId
	 * @param int $spaceId
	 * @param string $name
	 * @param string $content
	 * @return bool
	 */
	public function addPageTemplateContent( int $templateId, int $spaceId, string $name, string $content ): bool {
		$transaction = $this->cachedPrepare(
			'INSERT OR REPLACE INTO page_template_contents (
				template_id,
				space_id,
				name,
				content
			) VALUES (
				:template_id,
				:space_id,
				:name,
				:content
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':name', $name, SQLITE3_TEXT );
		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
		$transaction->bindValue( ':content', $content, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPageTemplateContents(): array {
		return $this->getAllData( 'page_template_contents' );
	}
}
