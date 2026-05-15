<?php

namespace HalloWelt\MigrateConfluence\Tests\Database;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class WorkspaceDbMock {

	/**
	 * @return WorkspaceDB
	 */
	public function create(): WorkspaceDB {
		$tempDir = sys_get_temp_dir() . '/confluence-migration-test-' . uniqid();

		$workspaceDB = new WorkspaceDB( $tempDir . '/workspace.sqlite' );

		$this->populateWorkspaceDB( $workspaceDB );

		return $workspaceDB;
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @return void
	 */
	private function populateWorkspaceDB( WorkspaceDB $workspaceDB ): void {
		// Spaces used throughout processor and utility tests.
		$workspaceDB->addSpace( 0, '', 'General', 'GENERAL:', -1, -1 );
		$workspaceDB->addSpace( 1, 'MKT', 'Marketing', 'MKT:', -1, -1 );
		$workspaceDB->addSpace( 23, 'DEVOPS', 'DevOps', 'DEVOPS:', -1, -1 );
		$workspaceDB->addSpace( 42, 'ABC', 'Some space', 'ABC:', -1, -1 );

		// Common pages used by link and macro tests.
		$workspaceDB->addPage(
			1001, 42, 'SomePage', 'ABC:SomePage', '2024-01-01T00:00:00.000Z', 'current',
			'1', -1, -1, [], [], []
		);
		$workspaceDB->addPage(
			1002, 42, 'SomeLinkedPage', 'ABC:SomeLinkedPage', '2024-01-01T00:00:00.000Z', 'current',
			'1', -1, -1, [], [], []
		);
		$workspaceDB->addPage(
			1003, 23, 'SomePage', 'DEVOPS:SomePage', '2024-01-01T00:00:00.000Z', 'current',
			'1', -1, -1, [], [], []
		);
		$workspaceDB->addPage(
			1004, 23, 'Some other page', 'DEVOPS:Some_other_page', '2024-01-01T00:00:00.000Z', 'current',
			'1', -1, -1, [], [], []
		);
		$workspaceDB->addPage(
			1005, 1, 'Brand Assets', 'MKT:Brand_Assets', '2024-01-01T00:00:00.000Z', 'current',
			'1', -1, -1, [], [], []
		);

		// Users for user-link related tests.
		$workspaceDB->addUser( 'abc123', 'Alice', 'alice@example.org', [] );
		$workspaceDB->addUser( 'def456', 'Bob', 'bob@example.org', [] );

		// Attachments for file/link/image and gallery style tests.
		$workspaceDB->addAttachment( 2001, 42, 'SomeImage.png', 'png', 1001, 'current', '', [] );
		$workspaceDB->addAttachment( 2002, 42, 'SomeImage1.png', 'png', 1001, 'current', '', [] );
		$workspaceDB->addAttachment( 2003, 23, 'SomeImage2.png', 'png', 1003, 'current', '', [] );
		$workspaceDB->addAttachment( 2004, 42, 'dashboard.png', 'png', 1001, 'current', '', [] );
		$workspaceDB->addAttachment( 2005, 42, 'photo.jpg', 'jpg', 1001, 'current', '', [] );

		$workspaceDB->addPageAttachment( 2001, 1001, 'SomeImage.png', 'ABC_SomePage_SomeImage.png' );
		$workspaceDB->addPageAttachment( 2002, 1001, 'SomeImage1.png', 'ABC_SomePage_SomeImage1.png' );
		$workspaceDB->addPageAttachment( 2003, 1003, 'SomeImage2.png', 'DEVOPS_SomePage_SomeImage2.png' );
		$workspaceDB->addPageAttachment( 2004, 1001, 'dashboard.png', 'dashboard.png' );
		$workspaceDB->addPageAttachment( 2005, 1001, 'photo.jpg', 'photo.jpg' );

		$workspaceDB->addAttachmentMeta( 2004, [
			'labels' => [],
			'mediaType' => 'image/png'
		] );
		$workspaceDB->addAttachmentMeta( 2005, [
			'labels' => [ 'featured', 'approved' ],
			'mediaType' => 'image/jpeg'
		] );
	}
}
