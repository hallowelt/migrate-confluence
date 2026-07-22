<?php

namespace HalloWelt\MigrateConfluence\Database;

use Exception;
use InvalidArgumentException;
use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;

class WorkspaceDB {

	/** @var SQLite3 */
	private SQLite3 $db;

	/** @var string */
	private const SQLITE_FILE = "workspace.sqlite";

	/** @var int */
	private const SQLITE_CONSTRAINT_ERROR_CODE = 19;

	/** @var array Cached prepared statements keyed by SQL string */
	private array $stmtCache = [];

	/** @var bool readonly mark this connection as read-only */
	private bool $readonly = false;

	/**
	 * @param string $dest
	 *
	 * @return self
	 * @throws RuntimeException if the file already exists
	 */
	public static function create( string $dest ): self {
		$dbPath = $dest . '/' . self::SQLITE_FILE;

		if ( file_exists( $dbPath ) ) {
			throw new RuntimeException( "Workspace DB already exists at '$dest'" );
		}
		return new self( $dest, false, true );
	}

	/**
	 * @param string $dest
	 * @param bool $readonly
	 *
	 * @return self
	 * @throws RuntimeException if the file does not exist
	 */
	public static function open( string $dest, bool $readonly = false ): self {
		$dbPath = $dest . '/' . self::SQLITE_FILE;

		if ( !file_exists( $dbPath ) ) {
			throw new RuntimeException(
				"Workspace DB not found at '$dest' — did you run the analyze step first?"
			);
		}
		return new self( $dest, $readonly, false );
	}

	/**
	 * @param string $name
	 * @param bool $readonly
	 * @param bool $create
	 */
	private function __construct( string $name, bool $readonly = false, bool $create = false ) {
		$this->readonly = $readonly;
		if ( $create ) {
			$flags = SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE;
		} else {
			$flags = $this->readonly ? SQLITE3_OPEN_READONLY : SQLITE3_OPEN_READWRITE;
		}
		$dbPath = $name . '/' . self::SQLITE_FILE;
		$this->db = new SQLite3( $dbPath, $flags );
		$this->db->enableExceptions( true );

		$this->db->busyTimeout( 5000 );
		if ( !$this->readonly ) {
			$this->db->exec( 'PRAGMA journal_mode = WAL' );
			$this->db->exec( 'PRAGMA synchronous = NORMAL' );
			$this->createTables();
		}
	}

	/**
	 * @return void
	 */
	public function beginTransaction(): void {
		if ( $this->readonly ) {
			return;
		}
		$this->db->exec( 'BEGIN TRANSACTION' );
	}

	/**
	 * @return void
	 */
	public function commitTransaction(): void {
		if ( $this->readonly ) {
			return;
		}
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
			} catch ( Exception $e ) {
				// Statement was in an error state from a previously failed execution; re-prepare it.
				$this->stmtCache[$sql] = $this->db->prepare( $sql );
			}
		}
		return $this->stmtCache[$sql];
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
			'page_invalid_titles',
			'pages_meta',
			'page_templates',
			'page_template_invalid_titles',
			'page_template_contents',
			'page_template_invalid_contents',
			'blog_posts',
			'blog_post_invalid_titles',
			'blog_posts_meta',
			'body_contents',
			'body_content_invalids',
			'attachments',
			'attachment_invalid_titles',
			'attachments_meta',
			'page_attachments',
			'blog_post_attachments',
			'additional_attachments',
			'users',
			'content_properties',
			'comments',
			'labellings',
			'labels',
			'gliffy',
		];

		if ( !in_array( $table, $allowedTables, true ) ) {
			throw new InvalidArgumentException( 'Table not allowed: ' . $table );
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
	 * If an error is cause bei unique constraint failure, proceed.
	 *
	 * @param SQLite3Stmt $transaction
	 * @return bool
	 */
	private function executeTransactionWithStatus( SQLite3Stmt $transaction ): bool {
		try {
			$result = $transaction->execute();
		} catch ( Exception $e ) {
			if ($e->getCode() === self::SQLITE_CONSTRAINT_ERROR_CODE) {
				$this->addLogEntry(
					'warning',
					'',
					__CLASS__,
					$e->getMessage()
				);

				return true;
			}

			return false;
		}

		$result->finalize();
		return true;
	}

	/**
	 * @return void
	 */
	private function createIndexes(): void {
		$this->doCreateIndex(
			'idx_body_contents_content_id', 'body_contents', 'content_id'
		);
		$this->doCreateIndex(
			'idx_attachments_container_id', 'attachments', 'container_id'
		);
		$this->doCreateIndex(
			'idx_page_attachments_target', 'page_attachments', 'target_attachment_filename'
		);
		$this->doCreateIndex(
			'idx_blog_post_attachments_target', 'blog_post_attachments', 'target_attachment_filename'
		);
		$this->doCreateIndex(
			'idx_additional_attachments_target', 'additional_attachments', 'target_attachment_filename'
		);
		$this->doCreateIndex(
			'idx_pages_space_id', 'pages', 'space_id'
		);
		$this->doCreateIndex(
			'idx_pages_space_title', 'pages', 'space_id, confluence_title'
		);
		$this->doCreateIndex(
			'idx_blog_posts_space_id', 'blog_posts', 'space_id'
		);
		$this->doCreateIndex(
			'idx_page_templates_template_id', 'page_templates', 'template_id'
		);
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
	 * @return void
	 */
	private function createTables(): void {
		// General logging
		$this->createTableLogging();

		// Tables to collect invalid titles or BodyContents ( e. g. content length)
		$this->createTableInvalidPageWikiTitles();
		$this->createTableInvalidBlogPostWikiTitles();
		$this->createTableInvalidBodyContents();
		$this->createTableInvalidAttachmentWikiTitles();
		$this->createTableInvalidPageTemplateTitles();
		$this->createTableInvalidPageTemplateContents();

		// Object tables
		$this->createTableSpaces();
		$this->createTableSpaceDescriptions();
		$this->createTablePages();
		$this->createTableBlogPosts();
		$this->createTableBodyContents();
		$this->createTableBodyContentBodies();
		$this->createTableAttachments();
		$this->createTablePageAttachments();
		$this->createTableBlogPostAttachments();
		$this->createTableAdditionalAttachments();
		$this->createTableUsers();
		$this->createTableContentProperties();
		$this->createTableComments();
		$this->createTablePageComments();
		$this->createTableBlogPostComments();
		$this->createTableLabellings();
		$this->createTableLabels();
		$this->createTableGliffy();
		$this->createTablePagesMeta();
		$this->createTableBlogPostsMeta();
		$this->createTableAttachmentsMeta();
		$this->createTablePageTemplates();
		$this->createTablePageTemplateContents();
		$this->createTableAttachmentsDescriptions();
		$this->createTableExportProperties();

		// Indexing tables
		$this->createIndexes();
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
	private function createTableInvalidPageWikiTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_invalid_titles (
				page_id INT PRIMARY KEY,
				wiki_title CHAR,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidBlogPostWikiTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_post_invalid_titles (
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
			'CREATE TABLE IF NOT EXISTS body_content_invalids (
				body_content_id INT PRIMARY KEY,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidPageTemplateTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_template_invalid_titles (
				template_id INT PRIMARY KEY,
				wiki_title CHAR,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidPageTemplateContents(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_template_invalid_contents (
				template_id INT PRIMARY KEY,
				text CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableInvalidAttachmentWikiTitles(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachment_invalid_titles (
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
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				revision_timestamp CHAR,
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
				parent_page_id INT,
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				revision_timestamp CHAR,
				historical_ids BLOB,
				last_modifier CHAR,
				body_content_ids BLOB,
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
				content_status CHAR,
				version CHAR,
				original_version_id INT,
				revision_timestamp CHAR,
				historical_ids BLOB,
				last_modifier CHAR,
				body_content_ids BLOB,
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
	 * $attachment id is not set as primary key because there can be multiple entries for the same attachment id
	 * if the attachment is attached to multiple pages with different names.
	 * @return void
	 */
	private function createTablePageAttachments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_attachments (
				attachment_id INT PRIMARY KEY,
				page_id INT,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	private function createTableBlogPostAttachments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_post_attachments (
				attachment_id INT PRIMARY KEY,
				blog_post_id INT,
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
				attachment_id INT PRIMARY KEY,
				original_attachment_filename CHAR,
				target_attachment_filename CHAR
			);'
		);
	}

	/**
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
	private function createTablePageComments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_comments (
				comment_id INT PRIMARY KEY,
				page_id INT,
				wiki_title CHAR
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableBlogPostComments(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS blog_post_comments (
				comment_id INT PRIMARY KEY,
				blog_post_id INT,
				wiki_title CHAR
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
	private function createTablePageTemplates(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS page_templates (
				template_id INT PRIMARY KEY,
				space_id INT,
				confluence_title CHAR,
				wiki_title CHAR,
				content_status CHAR,
				revision_timestamp CHAR,
				version CHAR,
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
				content TEXT
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createTableAttachmentsDescriptions(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS attachments_descriptions (
				attachment_id INT PRIMARY KEY,
				description TEXT
			);'
		);
	}

	/**
	 * @param int $attachmentId
	 * @param string $description
	 * @return void
	 */
	public function addAttachmentDescription( int $attachmentId, string $description ): void {
		$stmt = $this->cachedPrepare(
			'INSERT OR REPLACE INTO attachments_descriptions (attachment_id, description)
			VALUES (:attachment_id, :description)'
		);
		$stmt->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$stmt->bindValue( ':description', $description, SQLITE3_TEXT );
		$stmt->execute()->finalize();
	}

	/**
	 * @param int $attachmentId
	 * @return string
	 */
	public function getAttachmentDescription( int $attachmentId ): string {
		$stmt = $this->cachedPrepare(
			'SELECT description FROM attachments_descriptions WHERE attachment_id = :attachment_id'
		);
		$stmt->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();
		return $row !== false ? (string)$row['description'] : '';
	}

	/**
	 * @return void
	 */
	private function createTableExportProperties(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS export_properties (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				space_key CHAR,
				source CHAR,
				confluence_version CHAR,
				export_date CHAR,
				timezone_id CHAR,
				entities_xml_path CHAR
			);'
		);
	}

	/**
	 * @param string $spaceKey The Confluence space key (e.g. "HR"), from exportDescriptor.properties `spaceKey`
	 * @param string $source Export origin: "server" or "cloud"
	 * @param string $confluenceVersion Confluence version that created the export, e.g. "7.19.18"
	 * @param string $exportDate Raw timestamp from the properties comment line, e.g. "Mon May 18 11:58:00 CEST 2026"
	 * @param string $timezoneId Timezone of the exporting instance, e.g. "UTC" or "GMT"; empty for server exports
	 * @param string $entitiesXmlPath Last path segment and filename of the entities.xml, e.g. "my_space/entities.xml"
	 * @return void
	 */
	public function addExportProperties(
		string $spaceKey, string $source,
		string $confluenceVersion, string $exportDate,
		string $timezoneId, string $entitiesXmlPath
	): void {
		$stmt = $this->cachedPrepare(
			'INSERT INTO export_properties
				(space_key, source, confluence_version, export_date, timezone_id, entities_xml_path)
			VALUES
				(:space_key, :source, :confluence_version, :export_date, :timezone_id, :entities_xml_path)'
		);
		$stmt->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );
		$stmt->bindValue( ':source', $source, SQLITE3_TEXT );
		$stmt->bindValue( ':confluence_version', $confluenceVersion, SQLITE3_TEXT );
		$stmt->bindValue( ':export_date', $exportDate, SQLITE3_TEXT );
		$stmt->bindValue( ':timezone_id', $timezoneId, SQLITE3_TEXT );
		$stmt->bindValue( ':entities_xml_path', $entitiesXmlPath, SQLITE3_TEXT );
		$stmt->execute()->finalize();
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
	public function addInvalidPageWikiTitle( int $pageId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO page_invalid_titles (
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
	public function getInvalidPageWikiTitles(): array {
		return $this->getAllData( 'page_invalid_titles' );
	}

	/**
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 * @param string $text
	 * @return void
	 */
	public function addInvalidBlogPostWikiTitle( int $blogPostId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO blog_post_invalid_titles (
				page_id,
				wiki_title,
				text
			) VALUES (
				:page_id,
				:wiki_title,
				:text
			)'
		);

		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @return array
	 */
	public function getInvalidBlogPostWikiTitles(): array {
		return $this->getAllData( 'blog_post_invalid_titles' );
	}

	/**
	 * @param int $bodyContentId
	 * @param string $text
	 * @return void
	 */
	public function addInvalidBodyContent( int $bodyContentId, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO body_content_invalids (
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
		return $this->getAllData( 'body_content_invalids' );
	}

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @param string $text
	 * @return void
	 */
	public function addInvalidPageTemplateTitle( int $templateId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO page_template_invalid_titles (
				template_id,
				wiki_title,
				text
			) VALUES (
				:template_id,
				:wiki_title,
				:text
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * @param int $templateId
	 * @param string $text
	 * @return void
	 */
	public function addInvalidPageTemplateContent( int $templateId, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO page_template_invalid_contents (
				template_id,
				text
			) VALUES (
				:template_id,
				:text
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':text', $text, SQLITE3_TEXT );
		$transaction->execute();
	}

	/**
	 * Returns true if the page template is considered invalid.
	 *
	 * A page template is invalid if its wiki_title appears in page_template_invalid_titles,
	 * or if its content appears in page_template_invalid_contents.
	 *
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageTemplateInvalid( string $wikiTitle ): bool {
		// Check if the wiki title is in page templates
		$stmt = $this->cachedPrepare( 'SELECT template_id FROM page_templates WHERE wiki_title = :wiki_title LIMIT 1' );
		$stmt->bindValue( ':wiki_title', $wikiTitle );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row === false || !isset( $row['template_id'] ) ) {
			return true;
		}

		$templateId = $row['template_id'];

		// Check if the wiki_title of the template is in page_template_invalid_titles
		$stmt = $this->cachedPrepare(
			'SELECT template_id FROM page_template_invalid_titles
			WHERE template_id = :template_id
			LIMIT 1'
		);
		$stmt->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row !== false && isset( $row['template_id'] ) ) {
			return true;
		}

		// Check if the content of the template is in page_template_invalid_contents
		$stmt = $this->cachedPrepare(
			'SELECT template_id FROM page_template_invalid_contents
			WHERE template_id = :template_id
			LIMIT 1'
		);
		$stmt->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$invalidContentRow = $result->fetchArray( SQLITE3_ASSOC );

		return $invalidContentRow !== false && isset( $invalidContentRow['template_id'] );
	}

	/**
	 * Returns true if the page is considered invalid.
	 *
	 * A page is invalid if its wiki_title appears in page_invalid_titles,
	 * or if any of its body_content_ids are listed in body_content_invalids.
	 *
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isPageInvalid( string $wikiTitle ): bool {
		// Check if the wiki title is in page
		$stmt = $this->cachedPrepare( 'SELECT page_id FROM pages WHERE wiki_title = :wiki_title LIMIT 1' );
		$stmt->bindValue( ':wiki_title', $wikiTitle );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row === false || !isset( $row['page_id'] ) ) {
			return true;
		}

		$pageId = $row['page_id'];

		// Check if the wiki_title of the page is in page_invalid_titles
		$stmt = $this->cachedPrepare(
			'SELECT page_id FROM page_invalid_titles
			WHERE page_id = :page_id
			LIMIT 1'
		);
		$stmt->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row !== false && isset( $row['page_id'] ) ) {
			return true;
		}

		// Fetch body_content_ids for the page and all its historical versions
		$stmt = $this->cachedPrepare(
			'SELECT body_content_ids FROM pages
			WHERE page_id = :page_id OR original_version_id = :page_id'
		);
		$stmt->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$result = $stmt->execute();

		$bodyContentIds = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			if ( empty( $row['body_content_ids'] ) ) {
				$row = $result->fetchArray( SQLITE3_ASSOC );
				continue;
			}
			$ids = json_decode( $row['body_content_ids'], true );
			if ( is_array( $ids ) ) {
				$bodyContentIds = array_merge( $bodyContentIds, $ids );
			}
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}

		if ( count( $bodyContentIds ) === 0 ) {
			return false;
		}

		// Check if any body_content_id is listed as invalid
		foreach ( $bodyContentIds as $bodyContentId ) {
			$stmt = $this->cachedPrepare(
				'SELECT body_content_id FROM body_content_invalids
				WHERE body_content_id = :body_content_id
				LIMIT 1'
			);
			$stmt->bindValue( ':body_content_id', (int)$bodyContentId, SQLITE3_INTEGER );
			$result = $stmt->execute();
			$invalidRow = $result->fetchArray( SQLITE3_ASSOC );
			if ( $invalidRow !== false && isset( $invalidRow['body_content_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns all invalid pages with their space_id, page_id, confluence_title, wiki_title,
	 * and a concatenated text of all associated comments from page_invalid_titles and
	 * body_content_invalids.
	 *
	 * No historical version of a page is returned.
	 *
	 * @return array
	 */
	public function getInvalidPages( ?int $spaceId = null ): array {
		$result = [];

		// 1. Collect pages with invalid titles
		if ( $spaceId !== null ) {
			$stmt = $this->cachedPrepare(
				'SELECT p.page_id, p.space_id, p.confluence_title, p.wiki_title, pit.text
				FROM pages p
				INNER JOIN page_invalid_titles pit ON p.page_id = pit.page_id
				WHERE p.space_id = :space_id'
			);
			$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$stmt = $this->cachedPrepare(
				'SELECT p.page_id, p.space_id, p.confluence_title, p.wiki_title, pit.text
				FROM pages p
				INNER JOIN page_invalid_titles pit ON p.page_id = pit.page_id'
			);
		}

		$queryResult = $stmt->execute();
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$pageId = $row['page_id'];
			$result[$pageId] = [
				'page_id' => $pageId,
				'space_id' => $row['space_id'],
				'confluence_title' => $row['confluence_title'],
				'wiki_title' => $row['wiki_title'],
				'texts' => array_filter( [ $row['text'] ] ),
			];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		// 2. Load all invalid body content IDs and their comments
		$stmt = $this->cachedPrepare(
			'SELECT body_content_id, text FROM body_content_invalids'
		);
		$queryResult = $stmt->execute();
		$invalidBodyContents = [];
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$invalidBodyContents[(int)$row['body_content_id']] = $row['text'];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		if ( !empty( $invalidBodyContents ) ) {
			// 3. Load all pages (root and historical) to check their body_content_ids
			if ( $spaceId !== null ) {
				$stmt = $this->cachedPrepare(
					'SELECT page_id, space_id, confluence_title, wiki_title,
					        body_content_ids, original_version_id
					FROM pages
					WHERE space_id = :space_id'
				);
				$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
			} else {
				$stmt = $this->cachedPrepare(
					'SELECT page_id, space_id, confluence_title, wiki_title,
					        body_content_ids, original_version_id
					FROM pages'
				);
			}

			$queryResult = $stmt->execute();
			$allPages = [];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
			while ( $row !== false ) {
				$allPages[] = $row;
				$row = $queryResult->fetchArray( SQLITE3_ASSOC );
			}

			// Build a map of root page data keyed by page_id
			$rootPageData = [];
			foreach ( $allPages as $page ) {
				if ( empty( $page['original_version_id'] ) ) {
					$rootPageData[$page['page_id']] = $page;
				}
			}

			// Check each page's body_content_ids against the invalid set
			foreach ( $allPages as $page ) {
				if ( empty( $page['body_content_ids'] ) ) {
					continue;
				}
				$ids = json_decode( $page['body_content_ids'], true );
				if ( !is_array( $ids ) ) {
					continue;
				}
				$rootPageId = empty( $page['original_version_id'] )
					? $page['page_id']
					: (int)$page['original_version_id'];
				foreach ( $ids as $bodyContentId ) {
					$bodyContentId = (int)$bodyContentId;
					if ( !isset( $invalidBodyContents[$bodyContentId] ) ) {
						continue;
					}
					if ( !isset( $result[$rootPageId] ) ) {
						$rootPage = $rootPageData[$rootPageId] ?? null;
						if ( $rootPage === null ) {
							continue;
						}
						$result[$rootPageId] = [
							'page_id' => $rootPageId,
							'space_id' => $rootPage['space_id'],
							'confluence_title' => $rootPage['confluence_title'],
							'wiki_title' => $rootPage['wiki_title'],
							'texts' => [],
						];
					}
					$text = $invalidBodyContents[$bodyContentId];
					if ( !empty( $text ) && !in_array( $text, $result[$rootPageId]['texts'], true ) ) {
						$result[$rootPageId]['texts'][] = $text;
					}
				}
			}
		}

		// Flatten texts into a single string
		$pages = [];
		foreach ( $result as $page ) {
			$page['text'] = implode( "\n", $page['texts'] );
			unset( $page['texts'] );
			$pages[] = $page;
		}

		return $pages;
	}

	/**
	 * Returns all invalid blog posts with their space_id, page_id, confluence_title, wiki_title,
	 * and a concatenated text of all associated comments from blog_post_invalid_titles and
	 * body_content_invalids.
	 *
	 * No historical version of a blog post is returned.
	 *
	 * @return array
	 */
	public function getInvalidBlogPosts( ?int $spaceId = null ): array {
		$result = [];

		// 1. Collect blog posts with invalid titles
		if ( $spaceId !== null ) {
			$stmt = $this->cachedPrepare(
				'SELECT bp.page_id, bp.space_id, bp.confluence_title, bp.wiki_title, bpit.text
				FROM blog_posts bp
				INNER JOIN blog_post_invalid_titles bpit ON bp.page_id = bpit.page_id
				WHERE bp.space_id = :space_id'
			);
			$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$stmt = $this->cachedPrepare(
				'SELECT bp.page_id, bp.space_id, bp.confluence_title, bp.wiki_title, bpit.text
				FROM blog_posts bp
				INNER JOIN blog_post_invalid_titles bpit ON bp.page_id = bpit.page_id'
			);
		}

		$queryResult = $stmt->execute();
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$pageId = $row['page_id'];
			$result[$pageId] = [
				'page_id' => $pageId,
				'space_id' => $row['space_id'],
				'confluence_title' => $row['confluence_title'],
				'wiki_title' => $row['wiki_title'],
				'texts' => array_filter( [ $row['text'] ] ),
			];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		// 2. Load all invalid body content IDs and their comments
		$stmt = $this->cachedPrepare(
			'SELECT body_content_id, text FROM body_content_invalids'
		);
		$queryResult = $stmt->execute();
		$invalidBodyContents = [];
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$invalidBodyContents[(int)$row['body_content_id']] = $row['text'];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		if ( !empty( $invalidBodyContents ) ) {
			// 3. Load all blog posts (root and historical) to check their body_content_ids
			if ( $spaceId !== null ) {
				$stmt = $this->cachedPrepare(
					'SELECT page_id, space_id, confluence_title, wiki_title,
					        body_content_ids, original_version_id
					FROM blog_posts
					WHERE space_id = :space_id'
				);
				$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
			} else {
				$stmt = $this->cachedPrepare(
					'SELECT page_id, space_id, confluence_title, wiki_title,
					        body_content_ids, original_version_id
					FROM blog_posts'
				);
			}

			$queryResult = $stmt->execute();
			$allBlogPosts = [];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
			while ( $row !== false ) {
				$allBlogPosts[] = $row;
				$row = $queryResult->fetchArray( SQLITE3_ASSOC );
			}

			// Build a map of root blog post data keyed by page_id
			$rootBlogPostData = [];
			foreach ( $allBlogPosts as $blogPost ) {
				if ( empty( $blogPost['original_version_id'] ) ) {
					$rootBlogPostData[$blogPost['page_id']] = $blogPost;
				}
			}

			// Check each blog post's body_content_ids against the invalid set
			foreach ( $allBlogPosts as $blogPost ) {
				if ( empty( $blogPost['body_content_ids'] ) ) {
					continue;
				}
				$ids = json_decode( $blogPost['body_content_ids'], true );
				if ( !is_array( $ids ) ) {
					continue;
				}
				$rootPageId = empty( $blogPost['original_version_id'] )
					? $blogPost['page_id']
					: (int)$blogPost['original_version_id'];
				foreach ( $ids as $bodyContentId ) {
					$bodyContentId = (int)$bodyContentId;
					if ( !isset( $invalidBodyContents[$bodyContentId] ) ) {
						continue;
					}
					if ( !isset( $result[$rootPageId] ) ) {
						$rootBlogPost = $rootBlogPostData[$rootPageId] ?? null;
						if ( $rootBlogPost === null ) {
							continue;
						}
						$result[$rootPageId] = [
							'page_id' => $rootPageId,
							'space_id' => $rootBlogPost['space_id'],
							'confluence_title' => $rootBlogPost['confluence_title'],
							'wiki_title' => $rootBlogPost['wiki_title'],
							'texts' => [],
						];
					}
					$text = $invalidBodyContents[$bodyContentId];
					if ( !empty( $text ) && !in_array( $text, $result[$rootPageId]['texts'], true ) ) {
						$result[$rootPageId]['texts'][] = $text;
					}
				}
			}
		}

		// Flatten texts into a single string
		$blogPosts = [];
		foreach ( $result as $blogPost ) {
			$blogPost['text'] = implode( "\n", $blogPost['texts'] );
			unset( $blogPost['texts'] );
			$blogPosts[] = $blogPost;
		}

		return $blogPosts;
	}

	/**
	 * Returns all invalid attachments with their space_id, attachment_id, filename,
	 * wiki_title, and text comment from attachment_invalid_titles.
	 *
	 * @return array
	 */
	public function getInvalidAttachments( ?int $spaceId = null ): array {
		if ( $spaceId !== null ) {
			$stmt = $this->cachedPrepare(
				'SELECT a.attachment_id, a.space_id, a.filename, ait.wiki_title, ait.text
				FROM attachments a
				INNER JOIN attachment_invalid_titles ait ON a.attachment_id = ait.attachment_id
				WHERE a.space_id = :space_id'
			);
			$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$stmt = $this->cachedPrepare(
				'SELECT a.attachment_id, a.space_id, a.filename, ait.wiki_title, ait.text
				FROM attachments a
				INNER JOIN attachment_invalid_titles ait ON a.attachment_id = ait.attachment_id'
			);
		}

		$queryResult = $stmt->execute();
		$attachments = [];
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$attachments[] = [
				'attachment_id' => $row['attachment_id'],
				'space_id' => $row['space_id'],
				'confluence_title' => $row['filename'],
				'wiki_title' => $row['wiki_title'],
				'text' => $row['text'] ?? '',
			];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		return $attachments;
	}

	/**
	 * Returns all invalid page templates with their space_id, template_id, confluence_title,
	 * wiki_title, and a concatenated text of all associated comments from
	 * page_template_invalid_titles and page_template_invalid_contents.
	 *
	 * @return array
	 */
	public function getInvalidPageTemplates( ?int $spaceId = null ): array {
		$result = [];

		// 1. Collect templates with invalid titles
		if ( $spaceId !== null ) {
			$stmt = $this->cachedPrepare(
				'SELECT pt.template_id, pt.space_id, pt.confluence_title, pt.wiki_title, ptit.text
				FROM page_templates pt
				INNER JOIN page_template_invalid_titles ptit ON pt.template_id = ptit.template_id
				WHERE pt.space_id = :space_id'
			);
			$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$stmt = $this->cachedPrepare(
				'SELECT pt.template_id, pt.space_id, pt.confluence_title, pt.wiki_title, ptit.text
				FROM page_templates pt
				INNER JOIN page_template_invalid_titles ptit ON pt.template_id = ptit.template_id'
			);
		}

		$queryResult = $stmt->execute();
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$templateId = $row['template_id'];
			$result[$templateId] = [
				'template_id' => $templateId,
				'space_id' => $row['space_id'],
				'confluence_title' => $row['confluence_title'],
				'wiki_title' => $row['wiki_title'],
				'texts' => array_filter( [ $row['text'] ] ),
			];
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		// 2. Collect templates with invalid contents
		if ( $spaceId !== null ) {
			$stmt = $this->cachedPrepare(
				'SELECT pt.template_id, pt.space_id, pt.confluence_title, pt.wiki_title, ptic.text
				FROM page_templates pt
				INNER JOIN page_template_invalid_contents ptic ON pt.template_id = ptic.template_id
				WHERE pt.space_id = :space_id'
			);
			$stmt->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$stmt = $this->cachedPrepare(
				'SELECT pt.template_id, pt.space_id, pt.confluence_title, pt.wiki_title, ptic.text
				FROM page_templates pt
				INNER JOIN page_template_invalid_contents ptic ON pt.template_id = ptic.template_id'
			);
		}

		$queryResult = $stmt->execute();
		$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			$templateId = $row['template_id'];
			if ( !isset( $result[$templateId] ) ) {
				$result[$templateId] = [
					'template_id' => $templateId,
					'space_id' => $row['space_id'],
					'confluence_title' => $row['confluence_title'],
					'wiki_title' => $row['wiki_title'],
					'texts' => [],
				];
			}
			$text = $row['text'] ?? '';
			if ( !empty( $text ) && !in_array( $text, $result[$templateId]['texts'], true ) ) {
				$result[$templateId]['texts'][] = $text;
			}
			$row = $queryResult->fetchArray( SQLITE3_ASSOC );
		}

		// Flatten texts into a single string
		$templates = [];
		foreach ( $result as $template ) {
			$template['text'] = implode( "\n", $template['texts'] );
			unset( $template['texts'] );
			$templates[] = $template;
		}

		return $templates;
	}

	/**
	 * Returns true if the blog post is considered invalid.
	 *
	 * A blog post is invalid if its wiki_title appears in blog_post_invalid_titles,
	 * or if any of its body_content_ids are listed in body_content_invalids.
	 *
	 * @param string $wikiTitle
	 *
	 * @return bool
	 */
	public function isBlogPostInvalid( string $wikiTitle ): bool {
		// Check if the wiki title is in page
		$stmt = $this->cachedPrepare( 'SELECT page_id FROM blog_posts WHERE wiki_title = :wiki_title LIMIT 1' );
		$stmt->bindValue( ':wiki_title', $wikiTitle );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row === false || !isset( $row['page_id'] ) ) {
			return true;
		}

		$blogPostId = $row['page_id'];

		// Check if the wiki_title of the blog post is in blog_post_invalid_titles
		$stmt = $this->cachedPrepare(
			'SELECT page_id FROM blog_post_invalid_titles
			WHERE page_id = :page_id
			LIMIT 1'
		);
		$stmt->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row !== false && isset( $row['page_id'] ) ) {
			return true;
		}

		// Fetch body_content_ids for the blog post and all its historical versions
		$stmt = $this->cachedPrepare(
			'SELECT body_content_ids FROM blog_posts
			WHERE page_id = :page_id OR original_version_id = :page_id'
		);
		$stmt->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );
		$result = $stmt->execute();

		$bodyContentIds = [];
		$row = $result->fetchArray( SQLITE3_ASSOC );
		while ( $row !== false ) {
			if ( empty( $row['body_content_ids'] ) ) {
				$row = $result->fetchArray( SQLITE3_ASSOC );
				continue;
			}
			$ids = json_decode( $row['body_content_ids'], true );
			if ( is_array( $ids ) ) {
				$bodyContentIds = array_merge( $bodyContentIds, $ids );
			}
			$row = $result->fetchArray( SQLITE3_ASSOC );
		}

		if ( count( $bodyContentIds ) === 0 ) {
			return false;
		}

		// Check if any body_content_id is listed as invalid
		foreach ( $bodyContentIds as $bodyContentId ) {
			$stmt = $this->cachedPrepare(
				'SELECT body_content_id FROM body_content_invalids
				WHERE body_content_id = :body_content_id
				LIMIT 1'
			);
			$stmt->bindValue( ':body_content_id', (int)$bodyContentId, SQLITE3_INTEGER );
			$result = $stmt->execute();
			$invalidRow = $result->fetchArray( SQLITE3_ASSOC );
			if ( $invalidRow !== false && isset( $invalidRow['body_content_id'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param int $attachmentId
	 * @param string $wikiTitle
	 * @param string $text
	 * @return void
	 */
	public function addInvalidAttachmentTitle( int $attachmentId, string $wikiTitle, string $text ): void {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO attachment_invalid_titles (
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
		return $this->getAllData( 'attachment_invalid_titles' );
	}

	/**
	 * Returns true if the attachment is considered invalid.
	 *
	 * An attachment is invalid if its attachment_id appears in attachment_invalid_titles.
	 *
	 * @param int $attachmentId
	 * @return bool
	 */
	public function isAttachmentInvalid( int $attachmentId ): bool {
		$stmt = $this->cachedPrepare(
			'SELECT attachment_id FROM attachment_invalid_titles
			WHERE attachment_id = :attachment_id
			LIMIT 1'
		);
		$stmt->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$result = $stmt->execute();
		$row = $result->fetchArray( SQLITE3_ASSOC );
		if ( $row !== false && isset( $row['attachment_id'] ) ) {
			return true;
		}

		return false;
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
	 * @param int $spaceId
	 * @param int $homepageId
	 * @return bool True on success, false on error.
	 */
	public function updateSpaceHomepageId( int $spaceId, int $homepageId ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE spaces SET homepage_id = :homepage_id WHERE space_id = :space_id'
		);

		$transaction->bindValue( ':homepage_id', $homepageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $spaceId
	 * @return int|null The homepage_id for the given space_id, or null if not found.
	 */
	public function getSpaceHomepageIdForSpaceId( int $spaceId ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT homepage_id FROM spaces WHERE space_id = :space_id LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['homepage_id'] ) ) {
			return null;
		}

		return (int)$data['homepage_id'];
	}

	/**
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceMainPageWikiTitleForSpaceId( int $spaceId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT p.wiki_title
			FROM spaces s
			INNER JOIN pages p ON p.page_id = s.homepage_id
			WHERE s.space_id = :space_id
			LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['wiki_title'] ) ? $data['wiki_title'] : null;
	}

	/**
	 * @param int $spaceId
	 * @return string|null
	 */
	public function getSpaceKeyFromSpaceId( int $spaceId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT space_key FROM spaces WHERE space_id = :space_id LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['space_key'] ) ? $data['space_key'] : null;
	}

	/**
	 * @param int $descriptionId
	 * @return int|null
	 */
	public function getSpaceIdForDescriptionId( int $descriptionId ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM spaces WHERE description_id = :description_id LIMIT 1'
		);
		$transaction->bindValue( ':description_id', $descriptionId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return null;
		}

		return (int)$data['space_id'];
	}

	/**
	 * @param int $spaceDescriptionId
	 * @param string $contentStatus
	 * @param string $version
	 * @param int $originalVersionId
	 * @param string $revisionTimestamp
	 * @param array $bodyContentIds
	 * @param array $labellingIds
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addSpaceDescription(
		int $spaceDescriptionId, string $contentStatus, string $version,
		int $originalVersionId, string $revisionTimestamp, array $bodyContentIds,
		array $labellingIds, array $properties, array $collection
	): bool {
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$labellingIdsJson = json_encode( $labellingIds );
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT INTO spaces_descriptions (
				space_description_id,
				content_status,
				version,
				original_version_id,
				revision_timestamp,
				body_content_ids,
				labelling_ids,
				properties,
				collection
			) VALUES (
				:space_description_id,
				:content_status,
				:version,
				:original_version_id,
				:revision_timestamp,
				:body_content_ids,
				:labelling_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':space_description_id', $spaceDescriptionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':labelling_ids', $labellingIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param string $spaceKey
	 * @return int|null
	 */
	public function getSpaceIdFromSpaceKey( string $spaceKey ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM spaces WHERE space_key = :space_key LIMIT 1'
		);
		$transaction->bindValue( ':space_key', $spaceKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return null;
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
	public function getSpaceDescriptions(): array {
		return $this->getAllData( 'spaces_descriptions' );
	}

	/**
	 * @return array
	 */
	public function getCurrentSpaceDescriptions(): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM spaces_descriptions WHERE content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

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
			ORDER BY revision_timestamp ASC'
		);
		$transaction->bindValue( ':description_id', $descriptionId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
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
	 * @param int $pageId
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $contentStatus
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
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
		?int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $contentStatus,
		string $revisionTimestamp,
		string $lastModifier,
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
				parent_page_id,
				content_status,
				version,
				original_version_id,
				revision_timestamp,
				historical_ids,
				last_modifier,
				body_content_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:parent_page_id,
				:content_status,
				:version,
				:original_version_id,
				:revision_timestamp,
				:historical_ids,
				:last_modifier,
				:body_content_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':parent_page_id', $parentPageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
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
	 * @return array
	 */
	public function getPages(): array {
		return $this->getAllData( 'pages' );
	}

	/**
	 * @return array
	 */
	public function getCurrentPages(): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM pages WHERE content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( !$result ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM pages WHERE space_id = :space_id AND confluence_title = :confluence_title LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['wiki_title'] ) ? $data['wiki_title'] : null;
	}

	/**
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromSpaceId( int $spaceId, string $confluenceTitle ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM blog_posts
			WHERE space_id = :space_id
			AND confluence_title = :confluence_title
			LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['wiki_title'] ) ? $data['wiki_title'] : null;
	}

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getWikiPageTitleFromPageId( int $pageId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['wiki_title'] ) ? $data['wiki_title'] : null;
	}

	/**
	 * @return array
	 */
	public function getPageIdWikiPageTitleMap( ?int $spaceId = null ): array {
		if ( $spaceId === null ) {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title FROM pages
				 WHERE original_version_id = -1 AND content_status = :content_status'
			);
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title FROM pages WHERE original_version_id = -1
				 AND space_id = :space_id AND content_status = :content_status'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		}

		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );
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
	 * Returns current, non-historical pages with their id, wiki_title, confluence_title,
	 * parent_page_id and position extracted from the properties JSON blob.
	 *
	 * @param int|null $spaceId If given, only return pages for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title', 'parent_page_id', 'position']
	 */
	public function getPagesForSidebar( ?int $spaceId = null ): array {
		if ( $spaceId !== null ) {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title, confluence_title, parent_page_id, properties
				 FROM pages
				 WHERE original_version_id = -1 AND content_status = :content_status
				 AND space_id = :space_id'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title, confluence_title, parent_page_id, properties
				 FROM pages
				 WHERE original_version_id = -1 AND content_status = :content_status'
			);
		}
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		$rows = $this->fetchDbArray( $result );

		$pages = [];
		foreach ( $rows as $row ) {
			$props = json_decode( (string)$row['properties'], true ) ?? [];
			$pages[] = [
				'page_id'         => (int)$row['page_id'],
				'wiki_title'      => (string)$row['wiki_title'],
				'confluence_title' => (string)$row['confluence_title'],
				'parent_page_id'  => (int)$row['parent_page_id'],
				'position'        => isset( $props['position'] ) ? (int)$props['position'] : PHP_INT_MAX,
			];
		}

		return $pages;
	}

	/**
	 * Returns current, non-historical blog posts with their id, wiki_title and confluence_title.
	 * Blog posts have no parent hierarchy in Confluence, so no position/parent needed.
	 *
	 * @param int|null $spaceId If given, only return blog posts for that space.
	 * @return array Each entry: ['page_id', 'wiki_title', 'confluence_title']
	 */
	public function getBlogPostsForSidebar( ?int $spaceId = null ): array {
		if ( $spaceId !== null ) {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title, confluence_title
				 FROM blog_posts
				 WHERE original_version_id = -1 AND content_status = :content_status
				 AND space_id = :space_id'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title, confluence_title
				 FROM blog_posts
				 WHERE original_version_id = -1 AND content_status = :content_status'
			);
		}
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		$rows = $this->fetchDbArray( $result );

		$blogs = [];
		foreach ( $rows as $row ) {
			$blogs[] = [
				'page_id'         => (int)$row['page_id'],
				'wiki_title'      => (string)$row['wiki_title'],
				'confluence_title' => (string)$row['confluence_title'],
			];
		}

		return $blogs;
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
	 * @param int $pageId
	 * @return array
	 */
	public function getPageRevisionsForPageId( int $pageId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT revision_timestamp, version, body_content_ids FROM pages
			WHERE ( page_id = :page_id OR original_version_id = :page_id )
			AND content_status = :content_status
			ORDER BY revision_timestamp ASC'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	public function getConfluencePageTitleFromPageId( int $pageId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT confluence_title FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['confluence_title'] ) ? $data['confluence_title'] : null;
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
	 * @return int|null The space_id for the given page_id, or null if not found.
	 */
	public function getSpaceIdForPageId( int $pageId ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM pages WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return null;
		}

		return (int)$data['space_id'];
	}

	/**
	 * @param int $pageId
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $wikiTitle
	 * @param string $contentStatus
	 * @param string $revisionTimestamp
	 * @param string $lastModifier
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
		?int $spaceId,
		string $confluenceTitle,
		string $wikiTitle,
		string $contentStatus,
		string $revisionTimestamp,
		string $lastModifier,
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
				content_status,
				version,
				original_version_id,
				revision_timestamp,
				historical_ids,
				last_modifier,
				body_content_ids,
				properties,
				collection
			) VALUES (
				:page_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:content_status,
				:version,
				:original_version_id,
				:revision_timestamp,
				:historical_ids,
				:last_modifier,
				:body_content_ids,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':original_version_id', $originalVersionId, SQLITE3_INTEGER );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':historical_ids', $historicalIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':last_modifier', $lastModifier, SQLITE3_TEXT );
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
	 * @return array
	 */
	public function getCurrentBlogPosts(): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM blog_posts WHERE content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( !$result ) {
			return [];
		}

		return $this->fetchDbArray( $result );
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
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostIdWikiBlogPostTitleMap( ?int $spaceId = null ): array {
		if ( $spaceId === null ) {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title FROM blog_posts
				WHERE original_version_id = -1 AND content_status = :content_status'
			);
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT page_id, wiki_title FROM blog_posts
				 WHERE original_version_id = -1
				 AND space_id = :space_id AND content_status = :content_status'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		}

		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

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
	public function getBlogPostRevisionsForBlogPostId( int $blogPostId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT revision_timestamp, version, body_content_ids FROM blog_posts
			WHERE page_id = :page_id OR original_version_id = :page_id
			ORDER BY revision_timestamp ASC'
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
	 * @return int|null The space_id for the given blog post page_id, or null if not found.
	 */
	public function getSpaceIdForBlogPostId( int $blogPostId ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT space_id FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['space_id'] ) ) {
			return null;
		}

		return (int)$data['space_id'];
	}

	/**
	 * Get the target wiki title for a given blog post ID.
	 *
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getWikiBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['wiki_title'] ) ? $data['wiki_title'] : null;
	}

	/**
	 * Get the target confluence title for a given blog post ID.
	 *
	 * @param int $blogPostId
	 *
	 * @return string|null
	 */
	public function getConfluenceBlogPostTitleFromBlogPostId( int $blogPostId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT confluence_title FROM blog_posts WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( !$result ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		return !empty( $data['confluence_title'] ) ? $data['confluence_title'] : null;
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
	 * @return int|null
	 */
	public function getContentIdForBodyContentId( int $bodyContentId ): ?int {
		$transaction = $this->cachedPrepare(
			'SELECT content_id FROM body_contents WHERE body_content_id = :body_content_id'
		);
		$transaction->bindValue( ':body_content_id', $bodyContentId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$contentId = null;
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
	 * @param int|null $spaceId
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
		?int $spaceId,
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
		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
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
	 * @return array
	 */
	public function getCurrentAttachments(): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM attachments WHERE content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
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
			ORDER BY revision_timestamp ASC'
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
	 * @param int $blogPostId
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 * @return bool
	 */
	public function addBlogPostAttachment(
		int $attachmentId,
		int $blogPostId,
		string $originalAttachmentFilename,
		string $targetAttachmentFilename
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT INTO blog_post_attachments (
				attachment_id,
				blog_post_id,
				original_attachment_filename,
				target_attachment_filename
			) VALUES (
				:attachment_id,
				:blog_post_id,
				:original_attachment_filename,
				:target_attachment_filename
			)'
		);

		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':blog_post_id', $blogPostId, SQLITE3_INTEGER );
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
	 * Get the wiki file title for a given space id, confluence title and original attachment filename.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string|null
	 */
	public function getWikiFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): ?string {
		$result = $this->getPageWikiFileTitleFromSpaceId( $spaceId, $confluenceTitle, $originalAttachmentFilename );
		if ( $result === null ) {
			$result = $this->getBlogPostWikiFileTitleFromSpaceId(
				$spaceId, $confluenceTitle, $originalAttachmentFilename );
		}
		return $result;
	}

	/**
	 * Get the wiki file title for a given space id, confluence title and original attachment filename.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string|null
	 */
	private function getPageWikiFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): ?string {
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
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['target_attachment_filename'] ) ) {
			return null;
		}

		return $data['target_attachment_filename'];
	}

	/**
	 * Get the wiki file title for a given space id, confluence title and original attachment filename.
	 *
	 * @param int $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @return string|null
	 */
	private function getBlogPostWikiFileTitleFromSpaceId(
		int $spaceId, string $confluenceTitle, string $originalAttachmentFilename
	): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT bpa.target_attachment_filename FROM blog_post_attachments bpa
			JOIN blog_posts bp ON bpa.blog_post_id = bp.page_id
			WHERE bp.space_id = :space_id AND bp.confluence_title = :confluence_title
			AND bpa.original_attachment_filename = :original_attachment_filename
			LIMIT 1'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['target_attachment_filename'] ) ) {
			return null;
		}

		return $data['target_attachment_filename'];
	}

	/**
	 * get an attachment reference either from a page or a blog post
	 *
	 * @param string $attachmentTargetFileTitle
	 * @return string|null
	 */
	public function getAttachmentReference( string $attachmentTargetFileTitle ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT a.attachment_reference FROM attachments a
			LEFT JOIN page_attachments pa ON pa.attachment_id = a.attachment_id
			LEFT JOIN blog_post_attachments bpa ON bpa.attachment_id = a.attachment_id
			WHERE pa.target_attachment_filename = :target_attachment_filename
			OR bpa.target_attachment_filename = :target_attachment_filename
			ORDER BY a.revision_timestamp DESC
			LIMIT 1'
		);
		$transaction->bindValue( ':target_attachment_filename', $attachmentTargetFileTitle, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['attachment_reference'] ) ) {
			return null;
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
	public function getWikiFileTitlesForPage( int $spaceId, string $rawPageTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT pa.target_attachment_filename FROM attachments a
			JOIN pages p ON a.container_id = p.page_id
			JOIN page_attachments pa ON pa.attachment_id = a.attachment_id AND pa.page_id = p.page_id
			WHERE p.space_id = :space_id
			AND p.confluence_title = :confluence_title
			ORDER BY pa.target_attachment_filename ASC'
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
	 * Returns all target file titles attached to a given blog post.
	 *
	 * @param int $spaceId
	 * @param string $rawBlogPostTitle
	 * @return string[]
	 */
	public function getWikiFileTitlesForBlogPost( int $spaceId, string $rawBlogPostTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT bpa.target_attachment_filename FROM attachments a
			JOIN blog_posts b ON a.container_id = b.page_id
			JOIN blog_post_attachments bpa ON bpa.attachment_id = a.attachment_id AND bpa.blog_post_id = b.page_id
			WHERE b.space_id = :space_id
			AND b.confluence_title = :confluence_title
			ORDER BY bpa.target_attachment_filename ASC'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $rawBlogPostTitle, SQLITE3_TEXT );

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
	public function checkBlogPostAttachmentWikiTitleExists( string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT 1 FROM blog_post_attachments WHERE target_attachment_filename = :wiki_title LIMIT 1'
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
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageAttachments( ?int $spaceId = null ): array {
		if ( empty( $spaceId ) ) {
			return $this->getAllData( 'page_attachments' );
		}
		$transaction = $this->cachedPrepare(
			'SELECT pa.* FROM page_attachments pa
			JOIN attachments a ON pa.attachment_id = a.attachment_id
			WHERE a.space_id = :space_id'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getBlogPostAttachments( ?int $spaceId = null ): array {
		if ( empty( $spaceId ) ) {
			return $this->getAllData( 'blog_post_attachments' );
		}
		$transaction = $this->cachedPrepare(
			'SELECT bpa.* FROM blog_post_attachments bpa
			JOIN attachments a ON bpa.attachment_id = a.attachment_id
			WHERE a.space_id = :space_id'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getAdditionalAttachments( ?int $spaceId = null ): array {
		if ( empty( $spaceId ) ) {
			return $this->getAllData( 'additional_attachments' );
		}
		$transaction = $this->cachedPrepare(
			'SELECT aa.* FROM additional_attachments aa
			JOIN attachments a ON aa.attachment_id = a.attachment_id
			WHERE a.space_id = :space_id'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
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
	 * @param int $blogPostId
	 * @return array
	 */
	public function getBlogPostAttachmentsForBlogPostId( int $blogPostId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM blog_post_attachments WHERE blog_post_id = :blog_post_id'
		);
		$transaction->bindValue( ':blog_post_id', $blogPostId, SQLITE3_INTEGER );

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
	 * @return string|null
	 */
	public function getWikiTitleForAttachmentId( int $attachmentId ): ?string {
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
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return null;
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
	 * @return string|null
	 */
	public function getUsernameFromUserKey( string $userKey ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_user_name FROM users WHERE user_key = :user_key LIMIT 1'
		);
		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_user_name'] ) ) {
			return null;
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
	 * @param string $modified
	 * @param array $properties
	 * @return bool
	 */
	public function addComment(
		int $commentId, int $containerContentId, string $class, string $contentStatus,
		string $userKey, array $bodyContentIds, string $created, string $modified, array $properties
	): bool {
		$propertiesJson = json_encode( $properties );
		$bodyContentIdsJson = json_encode( $bodyContentIds );
		$transaction = $this->cachedPrepare(
			'INSERT INTO comments (
				comment_id,
				container_id,
				content_class,
				content_status,
				user_key,
				body_content_ids,
				created,
				modified,
				properties
			) VALUES (
				:comment_id,
				:container_id,
				:content_class,
				:content_status,
				:user_key,
				:body_content_ids,
				:created,
				:modified,
				:properties
			)'
		);

		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':container_id', $containerContentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content_class', $class, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':user_key', $userKey, SQLITE3_TEXT );
		$transaction->bindValue( ':body_content_ids', $bodyContentIdsJson, SQLITE3_TEXT );
		$transaction->bindValue( ':created', $created, SQLITE3_TEXT );
		$transaction->bindValue( ':modified', $modified, SQLITE3_TEXT );
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
	 * @return array
	 */
	public function getCurrentComments(): array {
		$transaction = $this->cachedPrepare(
			'SELECT * FROM comments WHERE content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * @param int $commentId
	 * @param int $pageId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function addPageComment( int $commentId, int $pageId, string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO page_comments (
				comment_id,
				page_id,
				wiki_title
			) VALUES (
				:comment_id,
				:page_id,
				:wiki_title
			)'
		);

		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $commentId
	 * @param int $blogPostId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function addBlogPostComment( int $commentId, int $blogPostId, string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'INSERT OR IGNORE INTO blog_post_comments (
				comment_id,
				blog_post_id,
				wiki_title
			) VALUES (
				:comment_id,
				:blog_post_id,
				:wiki_title
			)'
		);

		$transaction->bindValue( ':comment_id', $commentId, SQLITE3_INTEGER );
		$transaction->bindValue( ':blog_post_id', $blogPostId, SQLITE3_INTEGER );
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * Returns the wiki_title from page_comments for the given page ID, or null if not found.
	 *
	 * @param int $pageId
	 * @return string|null
	 */
	public function getWikiPageCommentTitleFromPageId( int $pageId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM page_comments WHERE page_id = :page_id LIMIT 1'
		);
		$transaction->bindValue( ':page_id', $pageId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return null;
		}

		return $data['wiki_title'];
	}

	/**
	 * Returns the wiki_title from blog_post_comments for the given blog post ID, or null if not found.
	 *
	 * @param int $blogPostId
	 * @return string|null
	 */
	public function getWikiBlogPostCommentTitleFromBlogPostId( int $blogPostId ): ?string {
		$transaction = $this->cachedPrepare(
			'SELECT wiki_title FROM blog_post_comments WHERE blog_post_id = :blog_post_id LIMIT 1'
		);
		$transaction->bindValue( ':blog_post_id', $blogPostId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return null;
		}

		$data = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();

		if ( $data === false || !isset( $data['wiki_title'] ) ) {
			return null;
		}

		return $data['wiki_title'];
	}

	/**
	 * Returns all page-level comments and the corresponding page wiki title.
	 *
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForPages( ?int $spaceId = null ): array {
		if ( $spaceId === null ) {
			$transaction = $this->cachedPrepare(
				'SELECT c.*, p.wiki_title AS wiki_title FROM comments c
				LEFT JOIN pages p ON p.page_id = c.container_id
				 WHERE c.content_class = :content_class
				 AND c.content_status = :content_status
				 AND p.content_status = :container_content_status'
			);
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT c.*, p.wiki_title AS wiki_title FROM comments c
				LEFT JOIN pages p ON p.page_id = c.container_id
				WHERE c.content_class = :content_class
				 AND c.content_status = :content_status
				 AND p.content_status = :container_content_status
				 AND p.space_id = :space_id'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		}

		$transaction->bindValue( ':content_class', 'Page', SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );
		$transaction->bindValue( ':container_content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
	}

	/**
	 * Returns all blog-post-level comments and the corresponding blog post wiki title.
	 *
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getCommentsForBlogPosts( ?int $spaceId = null ): array {
		if ( $spaceId === null ) {
			$transaction = $this->cachedPrepare(
				'SELECT c.*, bp.wiki_title AS wiki_title FROM comments c
				LEFT JOIN blog_posts bp ON bp.page_id = c.container_id
				WHERE c.content_class = :content_class
				AND c.content_status = :content_status
				AND bp.content_status = :container_content_status'
			);
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT c.*, bp.wiki_title AS wiki_title FROM comments c
				LEFT JOIN blog_posts bp ON bp.page_id = c.container_id
				WHERE c.content_class = :content_class
				AND c.content_status = :content_status
				AND bp.content_status = :container_content_status
				AND bp.space_id = :space_id'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		}

		$transaction->bindValue( ':content_class', 'BlogPost', SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );
		$transaction->bindValue( ':container_content_status', 'current', SQLITE3_TEXT );

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
	 * @param int $attachmentId
	 * @return array Decoded meta array, or empty array if not found.
	 */
	public function getAttachmentMetaById( int $attachmentId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT meta FROM attachments_meta WHERE attachment_id = :attachment_id LIMIT 1'
		);
		$transaction->bindValue( ':attachment_id', $attachmentId, SQLITE3_INTEGER );
		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}
		$row = $result->fetchArray( SQLITE3_ASSOC );
		$result->finalize();
		if ( $row === false || !isset( $row['meta'] ) ) {
			return [];
		}
		return json_decode( $row['meta'], true ) ?? [];
	}

	/**
	 * @param int|null $spaceId
	 * @param string $confluenceTitle
	 * @param string $originalAttachmentFilename
	 * @param string $targetAttachmentFilename
	 * @return bool
	 */
	public function addGliffy(
		?int $spaceId,
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

		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':original_attachment_filename', $originalAttachmentFilename, SQLITE3_TEXT );
		$transaction->bindValue( ':target_attachment_filename', $targetAttachmentFilename, SQLITE3_TEXT );

		return $this->executeTransactionWithStatus( $transaction );
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
	 * Returns target file titles with their full metadata for all attachments on a blog post.
	 * The returned array is keyed by confluence file key. Each value contains 'targetTitle'
	 * plus any additional metadata fields (e.g. 'labels', 'mediaType', etc.).
	 *
	 * @param int $spaceId
	 * @param string $rawBlogPostTitle
	 * @return string[] Map of confluenceFileKey => metadata (including 'targetTitle')
	 */
	public function getAttachmentMetadataForBlogPost( int $spaceId, string $rawBlogPostTitle ): array {
		$transaction = $this->cachedPrepare(
			'SELECT bpa.original_attachment_filename, bpa.target_attachment_filename, am.meta
			FROM blog_post_attachments bpa
			JOIN blog_posts bp ON bpa.blog_post_id = bp.page_id
			LEFT JOIN attachments_meta am ON bpa.attachment_id = am.attachment_id
			WHERE bp.space_id = :space_id AND bp.confluence_title = :confluence_title'
		);
		$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $rawBlogPostTitle, SQLITE3_TEXT );

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
	 * @param int $templateId
	 * @param string $confluenceTitle
	 * @param int|null $spaceId
	 * @param string $wikiTitle
	 * @param string $revisionTimestamp
	 * @param string $version
	 * @param array $properties
	 * @param array $collection
	 * @return bool
	 */
	public function addPageTemplate(
		int $templateId,
		string $confluenceTitle,
		?int $spaceId,
		string $wikiTitle = '',
		string $revisionTimestamp = '',
		string $version = '1',
		array $properties = [],
		array $collection = [],
		string $contentStatus = 'current'
	): bool {
		$propertiesJson = json_encode( $properties );
		$collectionJson = json_encode( $collection );
		$transaction = $this->cachedPrepare(
			'INSERT OR REPLACE INTO page_templates (
				template_id,
				space_id,
				confluence_title,
				wiki_title,
				content_status,
				revision_timestamp,
				version,
				properties,
				collection
			) VALUES (
				:template_id,
				:space_id,
				:confluence_title,
				:wiki_title,
				:content_status,
				:revision_timestamp,
				:version,
				:properties,
				:collection
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':confluence_title', $confluenceTitle, SQLITE3_TEXT );
		if ( $spaceId !== null ) {
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		} else {
			$transaction->bindValue( ':space_id', null, SQLITE3_NULL );
		}
		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':content_status', $contentStatus, SQLITE3_TEXT );
		$transaction->bindValue( ':revision_timestamp', $revisionTimestamp, SQLITE3_TEXT );
		$transaction->bindValue( ':version', $version, SQLITE3_TEXT );
		$transaction->bindValue( ':collection', $collectionJson, SQLITE3_TEXT );
		$transaction->bindValue( ':properties', $propertiesJson, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @param int $templateId
	 * @param string $wikiTitle
	 * @return bool True on success, false on error.
	 */
	public function updatePageTemplateWikiTitle( int $templateId, string $wikiTitle ): bool {
		$transaction = $this->cachedPrepare(
			'UPDATE page_templates SET wiki_title = :wiki_title WHERE template_id = :template_id'
		);

		$transaction->bindValue( ':wiki_title', $wikiTitle, SQLITE3_TEXT );
		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		return $this->executeTransactionWithStatus( $transaction );
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
	public function getTemplateTitleFromTemplateId( int $templateId ): ?string {
		$template = $this->getPageTemplateById( $templateId );
		if ( $template === null || empty( $template['wiki_title'] ) ) {
			return null;
		}

		return $template['wiki_title'];
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
	 * @param string $content
	 * @return bool
	 */
	public function addPageTemplateContents(
		int $templateId,
		string $content,
	): bool {
		$transaction = $this->cachedPrepare(
			'INSERT OR REPLACE INTO page_template_contents (
				template_id,
				content
			) VALUES (
				:template_id,
				:content
			)'
		);

		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );
		$transaction->bindValue( ':content', $content, SQLITE3_TEXT );
		return $this->executeTransactionWithStatus( $transaction );
	}

	/**
	 * @return array
	 */
	public function getPageTemplateContents(): array {
		return $this->getAllData( 'page_template_contents' );
	}

	/**
	 * @return array
	 */
	public function getCurrentPageTemplateContents(): array {
		$transaction = $this->cachedPrepare(
			'SELECT ptc.* FROM page_template_contents ptc
			INNER JOIN page_templates pt ON pt.template_id = ptc.template_id
			WHERE pt.content_status = :content_status'
		);
		$transaction->bindValue( ':content_status', 'current', SQLITE3_TEXT );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		return $this->fetchDbArray( $result );
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
	 * @param int|null $spaceId
	 * @return array
	 */
	public function getPageTemplateIdWikiTitleMap( ?int $spaceId = null ): array {
		if ( $spaceId === null ) {
			$transaction = $this->cachedPrepare(
				'SELECT template_id, wiki_title FROM page_templates'
			);
		} else {
			$transaction = $this->cachedPrepare(
				'SELECT template_id, wiki_title FROM page_templates
				 WHERE space_id = :space_id'
			);
			$transaction->bindValue( ':space_id', $spaceId, SQLITE3_INTEGER );
		}

		$result = $transaction->execute();
		$data = $this->fetchDbArray( $result );

		$map = [];
		foreach ( $data as $item ) {
			$map[$item['template_id']] = $item['wiki_title'];
		}

		return $map;
	}

	/**
	 * There are page template revisions but they are missing space_id, original_version_id
	 * or histroy_version_ids.
	 * It is not possible to link a template revision to its original template or other revisions,
	 * especially if more than one spaces are involved, but we can still return the revision info
	 * with the template_id as the template_content_id for the latest version for now.
	 *
	 * @param int $templateId
	 * @return array
	 */
	public function getPageTemplateRevisionsForTemplateId( int $templateId ): array {
		$transaction = $this->cachedPrepare(
			'SELECT template_id, revision_timestamp, version FROM page_templates
			WHERE template_id = :template_id
			ORDER BY revision_timestamp ASC'
		);
		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return [];
		}

		$data = $this->fetchDbArray( $result );

		// The template content ID for a template revision is its template ID.
		foreach ( $data as &$row ) {
			$templateRevisionId = isset( $row['template_id'] ) ? (int)$row['template_id'] : $templateId;
			$row['template_content_ids'] = json_encode( [ $templateRevisionId ] );
			unset( $row['template_id'] );
		}
		unset( $row );

		return $data;
	}

	/**
	 * @return array
	 */
	public function getPageTemplates(): array {
		return $this->getAllData( 'page_templates' );
	}

	/**
	 * @param int $templateId
	 * @return bool
	 */
	public function pageTemplateIdExists( int $templateId ): bool {
		$transaction = $this->cachedPrepare(
			'SELECT template_id FROM page_templates WHERE template_id = :template_id LIMIT 1'
		);
		$transaction->bindValue( ':template_id', $templateId, SQLITE3_INTEGER );

		$result = $transaction->execute();
		if ( $result === false ) {
			return false;
		}

		$exists = $result->fetchArray( SQLITE3_ASSOC ) !== false;
		$result->finalize();

		return $exists;
	}

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getWikiPageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		$template = $this->getPageTemplateById( $templateId );

		if ( $template === null || empty( $template['wiki_title'] ) ) {
			return null;
		}

		return $template['wiki_title'];
	}

	/**
	 * @param int $templateId
	 *
	 * @return string|null
	 */
	public function getConfluencePageTemplateTitleFromPageTemplateId( int $templateId ): ?string {
		$template = $this->getPageTemplateById( $templateId );

		if ( $template === null || empty( $template['confluence_title'] ) ) {
			return null;
		}

		return $template['confluence_title'];
	}
}
