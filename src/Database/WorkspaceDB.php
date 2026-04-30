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

	private function getArray( string $table ): array {
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
		$this->db->exec(
			"INSERT INTO spaces
			(space_id, space_key, space_name, space_prefix,
			homepage_id, description_id)
			VALUES
			(" . $spaceId . ", '" . $spaceKey ."', '" . $spaceName . "', '" . $prefix . "', "
			 . $homepageId . ", " . $descriptionId . ")"
		);
	}

	/**
	 * @return array
	 */
	public function getSpaces(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM spaces'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
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

		$this->db->exec(
			"INSERT INTO spaces_descriptions
			(space_description_id, body_content_ids, labelling_ids)
			VALUES
			(" . $spaceDescriptionId . ", '" . $bodyContentIdsJson ."', '" . $labellingIdsJson . "')"
		);
	}

	/**
	 * @return array
	 */
	public function getSpaceDescriptions(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM spaces_descriptions'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
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

		$this->db->exec(
			"INSERT INTO pages
			(page_id,space_id,confluence_title,wiki_title,revision_timestamp,content_status,original_version_id,parent_page_id,body_content_ids,properties,collection)
			VALUES
			($pageId,$spaceId,'$confluenceTitle','$wikiTitle','$revisionTimestamp','$contentStatus',$originalVersionId,$parentPageId,'$bodyContentIdsJson','$propertiesJson','$collectionJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getPages(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM pages'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
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

		$this->db->exec(
			"INSERT INTO blog_posts
			(page_id,space_id,confluence_title,wiki_title,revision_timestamp,content_status,original_version_id,body_content_ids,properties,collection)
			VALUES
			($pageId,$spaceId,'$confluenceTitle','$wikiTitle','$revisionTimestamp','$contentStatus',$originalVersionId,'$bodyContentIdsJson','$propertiesJson','$collectionJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getBlogPosts(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM blog_posts'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
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

		$this->db->exec(
			"INSERT INTO body_contents
			(body_content_id,page_id,class,properties)
			VALUES
			($bodyContentId,$pageId,'$class','$propertiesJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getBodyContents(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM body_contents'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param integer $pageId
	 * @return array
	 */
	public function getBodyContentIdsForPageId( int $pageId ): array {
		$transaction = $this->db->prepare(
			"SELECT body_content_id FROM body_contents WHERE page_id=$pageId"
		);

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

		$this->db->exec(
			"INSERT INTO attachments
			(attachment_id,space_id,filename,container_id,content_status,attachment_reference,properties)
			VALUES
			($attachmentId,$spaceId,'$filename',$containerContentId,'$contentStatus','$attachmentReference','$propertiesJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getAttachments(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM attachments'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	public function addUser(
		string $userKey,
		string $wikiUsername,
		string $email,
		array $properties
	): void {
		$propertiesJson = json_encode( $properties );

		$this->db->exec(
			"INSERT INTO users
			(user_key,wiki_user_name,email,properties)
			VALUES
			('$userKey','$wikiUsername','$email','$propertiesJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getUsers(): array {
		return $this->getArray( 'users' );
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

		$this->db->exec(
			"INSERT INTO content_properties
			(property_name,content_class,properties)
			VALUES
			('$propertyName','$class','$propertiesJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getContentProperties(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM content_properties'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
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

		$this->db->exec(
			"INSERT INTO comments
			(comment_id,content_id,content_class,user_key,created,modified,properties)
			VALUES
			('$commentId','$containerContentId','$class'','$userKey'','$created'','$propertiesJson')"
		);
	}

	/**
	 * @return array
	 */
	public function getComments(): array {
		$transaction = $this->db->prepare(
			'SELECT * FROM comments'
		);

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}
}