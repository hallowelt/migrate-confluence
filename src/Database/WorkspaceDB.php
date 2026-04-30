<?php

namespace HalloWelt\MigrateConfluence\Database;

use SQLite3;
use SQLite3Result;

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
			'spaces',
			'spaces_descriptions',
			'pages',
			'blog_posts',
			'body_contents',
			'attachments',
			'users',
			'content_properties',
			'comments',
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
		while($res = $result->fetchArray( SQLITE3_ASSOC ) ) {
			$data[] = $res;
		}
		return $data;
	}

	/**
	 * @return void
	 */
	private function createTables(): void {
		$this->createSpaceTable();
		$this->createSpaceDescriptionTable();
		$this->createPageTable();
		$this->createBlogPostTable();
		$this->createBodyContentTable();
		$this->createAtachementTable();
		$this->createUserTable();
		$this->createContentPropertyTable();
		$this->createCommentTable();
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
				user_key CHAR,
				created CHAR,
				modified CHAR,
				properties BLOB
			);'
		);
	}

	/**
	 * @param integer $spaceId
	 * @param string $spaceKey
	 * @param string $spaceName
	 * @param string $prefix
	 * @param integer $homepageId
	 * @param integer $descriptionId
	 * @return void
	 */
	public function addSpace(
		int $spaceId, string $spaceKey, string $spaceName,
		string $prefix, int $homepageId, int $descriptionId
	): void {
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
		$transaction->execute();
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
	 * @return void
	 */
	public function addSpaceDescription(
		int $spaceDescriptionId, array $bodyContentIds, array $lagellingIds
	): void {
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
		$transaction->execute();
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
	 * @param integer $originalVersionId
	 * @param integer $parentPageId
	 * @param array $bodyContentIds
	 * @param array $properties
	 * @param array $collection
	 * @return void
	 */
	public function addPage(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $contentStatus,
		int $originalVersionId,	
		int $parentPageId,		
		array $bodyContentIds,
		array $properties,
		array $collection
	): void {
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
		$transaction->bindValue( ':parent_page_id', $parentPageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		$transaction->execute();
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
	 * @return void
	 */
	public function updatePageWikiTitle( int $pageId, string $wikiTitle ): void {
		$transaction = $this->db->prepare(
			'UPDATE pages SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->execute();
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
	 * @return void
	 */
	public function addBlogPost(
		int $pageId,
		int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $revisionTimestamp,
		string $contentStatus,
		int $originalVersionId,	
		array $bodyContentIds,
		array $properties,
		array $collection
	): void {
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
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		$transaction->execute();
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
	 * @return void
	 */
	public function updateBlogPostWikiTitle( int $pageId, string $wikiTitle ): void {
		$transaction = $this->db->prepare(
			'UPDATE blog_posts SET wiki_title = :wiki_title WHERE page_id = :page_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->execute();
	}

	/**
	 * @param integer $bodyContentId
	 * @param integer $pageId
	 * @param string $class
	 * @param array $properties
	 * @return void
	 */
	public function addBodyContent(
		int $bodyContentId,
		int $pageId,
		string $class,
		array $properties
	): void {
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
		$transaction->execute();
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
	 * @param integer $attachmentId
	 * @param integer $spaceId
	 * @param string $filename
	 * @param integer $containerContentId
	 * @param string $contentStatus
	 * @param string $attachmentReference
	 * @param array $properties
	 * @return void
	 */
	public function addAttachment(
		int $attachmentId,
		int $spaceId,
		string $filename,
		int $containerContentId,
		string $contentStatus,
		string $attachmentReference,
		array $properties
	): void {
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
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getAttachments(): array {
		return $this->getAllData( 'attachments' );
	}

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): void {
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
		$transaction->execute();
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
	 * @return void
	 */
	public function addContentProperty(
		string $propertyName,
		string $class,
		array $properties
	): void {
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
		$transaction->execute();
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
	 * @param string $userKey
	 * @param string $created
	 * @param string $modiefied
	 * @param array $properties
	 * @return void
	 */
	public function addComment(
		int $commentId, int $containerContentId, string $class,
		string $userKey, string $created, string $modiefied, array $properties
	): void {
		$propertiesJson = json_encode( $properties );
		$transaction = $this->db->prepare(
			'INSERT INTO comments (
				comment_id,
				container_id,
				content_class,
				user_key,
				created,
				modified,
				properties
			) VALUES (
				:comment_id,
				:container_id,
				:content_class,
				:user_key,
				:created,
				:modified,
				:properties
			)'
		);

		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':container_id', $containerContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_class', $class, SQLITE3_TEXT );
		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );
		$transaction->bindValue( ':created', $created, SQLITE3_TEXT );
		$transaction->bindValue( ':modified', $modiefied, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getComments(): array {
		return $this->getAllData( 'comments' );
	}
}