<?php

namespace HalloWelt\MigrateConfluence\Database;

class AnalyzerDB extends MigrateConfluenceDB {

	/**
	 * @return void
	 */
	protected function createTables(): void {
		$this->createParentPagesTable();
		$this->createPageIdConfluenceTitleTable();
	}

	/**
	 * @return void
	 */
	private function createParentPagesTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS parent_pages (
				page_id INTEGER PRIMARY KEY,
				parent_page_id INTEGER
			);'
		);
	}

	/**
	 * @return void
	 */
	private function createPageIdConfluenceTitleTable(): void {
		$this->db->exec(
			'CREATE TABLE IF NOT EXISTS parent_pages (
				page_id INTEGER PRIMARY KEY,
				confluence_title Text
			);'
		);
	}

	public function mapPageIdToParentPageId( int $pageId, int $parentPageId ): void {
		$this->db->exec(
			"INSERT INTO parent_pages
			(page_id, parent_page_id)
			VALUES
			(" . $pageId . ", " . $parentPageId . ")"
		);
	}

	public function mapPageIdToConfluenceTitle( int $pageId, string $confluenceTitle ): void {
		$this->db->exec(
			"INSERT INTO page_id_to_confluence_title
			(page_id, parent_page_id)
			VALUES
			(" . $pageId . ", '" . $confluenceTitle . "')"
		);
	}
}