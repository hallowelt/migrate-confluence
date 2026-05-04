<?php

namespace HalloWelt\MigrateConfluence\Database;

use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

class WorkspaceDB {

	/** @var SQLite3 */
	private SQLite3 $db;

	/**
	 * @param string $name
	 */
	public function __construct( string $name ) {
		$this->db = new SQLite3( $name );

		$this->createTables();
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
			'blog_posts',
			'body_contents',
			'attachments',
			'page_attachments',
			'users',
			'content_properties',
			'comments',
			'labellings',
			'labels',
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
		while ( $res = $result->fetchArray( SQLITE3_ASSOC ) ) {
			$data[] = $res;
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
		$result = $transaction->execute();

		if ( $result === false ) {
			return false;
		}

		$result->finalize();
		return true;
	}

	/**
	 * @return void
	 */
	private function createTables(): void {
		$this->createLoggingTable();
		$this->createSpaceTable();
		$this->createSpaceDescriptionTable();
		$this->createPageTable();
		$this->createBlogPostTable();
		$this->createBodyContentTable();
		$this->createBodyContentBodyTable();
		$this->createAtachementTable();
		$this->createPageAttachmentTable();
		$this->createUserTable();
		$this->createContentPropertyTable();
		$this->createCommentTable();
		$this->createLabellingTable();
		$this->createLabelTable();
		$this->createPagesMetaTable();
		$this->createBlogPostsMetaTable();
		$this->createAttachmentsMetaTable();
	}

		/**
	 * @return void
	 */
	private function createLoggingTable(): void {
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
	private function createSpaceTable(): void {
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
	private function createSpaceDescriptionTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS spaces_descriptions (
				space_description_id INT PRIMARY KEY,
				body_content_ids BLOB,
				labelling_ids BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createPageTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS pages (
				page_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				revision_timestamp CHAR,
				content_status CHAR,
				original_version_id INT,
				version CHAR,
				parent_page_id INT,		
				body_content_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createBlogPostTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_posts (
				page_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				revision_timestamp CHAR,
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				body_content_ids BLOB,
				properties BLOB,
				collection BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createBodyContentTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS body_contents (
				body_content_id INT PRIMARY KEY,
				page_id INT,
				class CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createBodyContentBodyTable(): void {
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
	private function createAtachementTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachments (
				attachment_id INT PRIMARY KEY,
				space_id INT,
				filename CHAR,
				container_id INT,
				content_status CHAR,
				attachment_reference CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createPageAttachmentTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_attachments (
				attachment_id INT PRIMARY KEY,
				page_id INT,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createUserTable(): void {
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
	private function createContentPropertyTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS content_properties (
				property_name CHAR,
				content_class CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createCommentTable(): void {
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
	private function createLabellingTable(): void {
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
	private function createLabelTable(): void {
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
	private function createPagesMetaTable(): void {
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
	private function createBlogPostsMetaTable(): void {
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
	private function createAttachmentsMetaTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachments_meta (
				attachment_id INT PRIMARY KEY,
				meta BLOB
			);'
		);
	}

	/**
	 * @param string $type
	 * @param string $caller
	 * @param string $text
	 * @return void
	 */
	public function addLogEntry(
		string $type, string $step, string $caller, string $text
	): void {
		$transaction = $this->db->prepare(
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
	 * @return array
	 */
	public function getLogEntriesForStep( string $step ): array {
		$transaction = $this->db->prepare(
			'SELECT type,caller,text FROM logging WHERE step = :step'
		);
		$transaction->bindValue( ':step', $step, SQLITE3_TEXT );

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
		
	}

	/**
	 * @param integer $spaceId
	 * @param string $spaceKey
	 * @param string $spaceName
	 * @param string $prefix
	 * @param integer $homepageId
	 * @param integer $descriptionId
	 * @return bool True on success, false on error.
	 */
	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): bool {
		$transaction = $this->db->prepare(
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

	/**
	 * @return array
	 */
	public function getMapSpaceIdToPrefix(): array {
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
	 * @param integer $spaceDescriptionId
	 * @param array $bodyContentIds
	 * @param array $lagellingIds
	 * @return bool True on success, false on error.
	 */
	public function addSpaceDescription(
		int $spaceDescriptionId, array $bodyContentIds, array $lagellingIds
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$labellingIdsJson = json_encode( $lagellingIds );
		$transaction = $this->db->prepare(
			'INSERT INTO spaces_descriptions (
				space_description_id,
				body_content_ids,
				labelling_ids
			) VALUES (
				:space_description_id,
				:body_content_ids,
				:labelling_ids
			)'
		);

		$transaction->bindValue( ':space_description_id', $spaceDescriptionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':labelling_ids', $labellingIdsJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getSpaceDescriptions(): array {
		return $this->getAllData( 'spaces_descriptions' );
	}

	/**
	 * @param integer $pageId
	 * @param integer $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $contentStatus
	 * @param string $version
	 * @param integer $originalVersionId
	 * @param integer $parentPageId
	 * @param array $bodyContentIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool True on success, false on error.
	 */
	public function addPage(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $contentStatus,
		string $version,
		int $originalVersionId,	
		int $parentPageId,		
		array $bodyContentIds,
		array $properties,
		array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->db->prepare(
			'INSERT INTO pages (
				page_id,
				space_id,
				confluence_title,
				wiki_title,
				revision_timestamp,
				content_status,
				original_version_id,
				version,
				parent_page_id,
				body_content_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:revision_timestamp,
				:content_status,
				:original_version_id,
				:version,
				:parent_page_id,
				:body_content_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':parent_page_id', $parentPageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPages(): array {
		return $this->getAllData( 'pages' );
	}

	/**
	 * @return array
	 */
	public function getMapPageIdtoParentPageId(): array {
		$transaction = $this->db->prepare(
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
	 * @param integer $pageId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): bool {
		$transaction = $this->db->prepare(
			'UPDATE pages SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getMapPageIdToConfluenceTitle(): array {
		$transaction = $this->db->prepare(
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
	 * @param integer $pageId
	 * @param integer $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $contentStatus
	 * @param integer $originalVersionId
	 * @param array $bodyContentIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool True on success, false on error.
	 */
	public function addBlogPost(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $contentStatus,
		string $version,
		int $originalVersionId,	
		array $bodyContentIds,
		array $properties,
		array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->db->prepare(
			'INSERT INTO blog_posts (
				page_id,
				space_id,
				confluence_title,
				wiki_title,
				revision_timestamp,
				content_status,
				version,
				original_version_id,
				body_content_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:revision_timestamp,
				:content_status,
				:version,
				:original_version_id,
				:body_content_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getBlogPosts(): array {
		return $this->getAllData( 'blog_posts' );
	}

	/**
	 * @param integer $pageId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): bool {
		$transaction = $this->db->prepare(
			'UPDATE blog_posts SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param integer $bodyContentId
	 * @param integer $pageId
	 * @param string $class
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addBodyContent(
		int $bodyContentId,
		int $pageId,
		string $class,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
			'INSERT INTO body_contents (
				body_content_id,
				page_id,
				class,
				properties
			) VALUES (
				:body_content_id,
				:page_id,
				:class,
				:properties
			)'
		);

		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
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
	 * @param integer $pageId
	 * @return array
	 */
	public function getBodyContentIdsForPageId( int $pageId ): array {
		$transaction = $this->db->prepare(
			'SELECT body_content_id FROM body_contents WHERE page_id = :page_id'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

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
	 * @param integer $bodyContentId
	 * @param string $body
	 * @return bool True on success, false on error.
	 */
	public function addBodyContentBody(
		int $bodyContentId,
		string $body
	): bool {
		$transaction = $this->db->prepare(
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
	 * @param integer $bodyContentId
	 * @return array
	 */
	public function getBodyForBodyContentId( int $bodyContentId ): array {
		$transaction = $this->db->prepare(
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
	 * @param integer $attachmentId
	 * @param integer $spaceId
	 * @param string $filename
	 * @param integer $containerContentId
	 * @param string $contentStatus
	 * @param string $attachmentReference
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addAttachment(
		int $attachmentId,
		int $spaceId,
		string $filename,
		int $containerContentId,
		string $contentStatus,
		string $attachmentReference,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
			'INSERT INTO attachments (
				attachment_id,
				space_id,
				filename,
				container_id,
				content_status,
				attachment_reference,
				properties
			) VALUES (
				:attachment_id,
				:space_id,
				:filename,
				:container_id,
				:content_status,
				:attachment_reference,
				:properties
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':filename', $filename, SQLITE3_TEXT );
		$transaction->bindValue( ':container_id', $containerContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':attachment_reference', $attachmentReference, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getAttachments(): array {
		return $this->getAllData( 'attachments' );
	}

	/**
	 * @param integer $attachmentId
	 * @param integer $pageId
	 * @param string $targetAttachmentFilename
	 * @return bool True on success, false on error.
	 */
	public function addPageAttachment(
		int $attachmentId,
		int $pageId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		$transaction = $this->db->prepare(
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
	 * @param string $wikiTitle
	 * @return boolean
	 */
	public function checkPageAttachmentWikiTitleExists( string $wikiTitle ): bool {	
		$transaction = $this->db->prepare(
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
	 * @return array
	 */
	public function getPageAttachments(): array {
		return $this->getAllData( 'page_attachments' );
	}

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
			'INSERT INTO users (
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
	 * @param string $propertyName
	 * @param string $class
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addContentProperty(
		string $propertyName,
		string $class,
		array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
			'INSERT INTO content_properties (
				property_name,
				content_class,
				properties
			) VALUES (
				:property_name,
				:content_class,
				:properties
			)'
		);

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
	 * @param integer $commentId
	 * @param integer $containerContentId
	 * @param string $class
	 * @param string $contentStatus
	 * @param string $userKey
	 * @param string $bodyContentIds
	 * @param string $created
	 * @param string $modiefied
	 * @param array $properties
	 * @return boolean
	 */
	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, string $bodyContentIds, string $created, string $modiefied, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->db->prepare(
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
	 * @return array
	 */
	public function getComments(): array {
		return $this->getAllData( 'comments' );
	}

	/**
	 * @param integer $labellingId
	 * @param integer $labelId
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addLabelling(
		int $labellingId, int $labelId, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
	 * @param integer $labelId
	 * @param string $name
	 * @param string $namespace
	 * @param array $properties
	 * @return bool True on success, false on error.
	 */
	public function addLabel(
		int $labelId, string $name, string $namespace, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
	 * @param integer $pageId
	 * @param array $meta
	 * @return bool True on success, false on error.
	 */
	public function addPageMeta(
		int $pageId, array $meta
	): bool {
		$metaJson = json_encode( $meta );
		$transaction = $this->db->prepare(
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
	 * @param integer $pageId
	 * @param array $meta
	 * @return bool True on success, false on error.
	 */
	public function addBlogPostMeta(
		int $pageId, array $meta
	): bool {
		$metaJson = json_encode( $meta );
		$transaction = $this->db->prepare(
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
		$transaction = $this->db->prepare(
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
}