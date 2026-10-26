<?php

namespace HalloWelt\MigrateConfluence\Extractor\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Extractor\DataWriter\IExtractorDataWriter;
use HalloWelt\MigrateConfluence\Extractor\ProcessorBase;
use HalloWelt\MigrateConfluence\Utility\DBLog;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use HalloWelt\MigrateConfluence\Utility\PageConstraintsResolver;

/**
 * Stores the effective page restrictions of every current page and blog post.
 */
class ExtractPageConstraints extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param DBLog $dbLog
	 * @param IExtractorDataWriter $writer
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		WorkspaceDB $workspaceDB,
		DBLog $dbLog,
		IExtractorDataWriter $writer,
		protected MigrationConfig $migrationConfig
	) {
		parent::__construct( $workspaceDB, $dbLog, $writer );
	}

	/**
	 * @return void
	 */
	public function execute(): void {
		$resolver = new PageConstraintsResolver(
			$this->collectRestrictions(),
			$this->workspaceDB->getMapPageIdtoParentPageId(),
			fn ( string $userKey ) => $this->workspaceDB->getUsernameFromUserKey( $userKey ),
			fn ( string $group ) => $this->workspaceDB->getWikiGroupName( $group ),
			$this->dbLog
		);

		$contents = array_merge(
			$this->workspaceDB->getCurrentPages(),
			$this->workspaceDB->getCurrentBlogPosts()
		);
		foreach ( $contents as $content ) {
			$id = (int)( $content['page_id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$constraints = $resolver->resolve( $id );
			$this->writer->addPageConstraints(
				$id,
				$constraints['type'],
				$constraints['timestamp'],
				$constraints['targets']
			);
		}
	}

	/**
	 * @return array<int, array{view: ?array, edit: ?array}>
	 */
	private function collectRestrictions(): array {
		$permissionsById = [];
		$permissionsBySet = [];
		foreach ( $this->workspaceDB->getContentPermissions() as $row ) {
			$permission = [
				'user' => (string)$row['user_key'],
				'group' => (string)$row['group_name'],
				'timestamp' => (string)$row['timestamp'],
			];
			$permissionsById[(int)$row['permission_id']] = $permission;
			if ( (int)$row['set_id'] > 0 ) {
				$permissionsBySet[(int)$row['set_id']][(int)$row['permission_id']] = $permission;
			}
		}

		$restrictions = [];
		foreach ( $this->workspaceDB->getContentPermissionSets() as $set ) {
			$contentId = (int)$set['content_id'];
			$type = strtolower( (string)$set['permission_type'] );
			if ( $contentId <= 0 || !in_array( $type, [ 'view', 'edit' ], true ) ) {
				// Share and unknown types have no counterpart in the wiki
				$this->dbLog->addLogEntry(
					'warning',
					'extract',
					__CLASS__,
					"Permission set {$set['set_id']} (content: $contentId, type: '{$set['permission_type']}') ignored."
				);
				continue;
			}

			$list = $permissionsBySet[(int)$set['set_id']] ?? [];
			foreach ( json_decode( (string)$set['permission_ids'], true ) ?: [] as $permissionId ) {
				if ( isset( $permissionsById[$permissionId] ) ) {
					$list[$permissionId] = $permissionsById[$permissionId];
				}
			}
			// A set without any members still counts as a restriction: fail closed
			$restrictions[$contentId][$type] = array_merge( $restrictions[$contentId][$type] ?? [], $list );
		}

		return $restrictions;
	}
}
