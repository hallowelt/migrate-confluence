<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\DataBuckets;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;

class ConversionDataWriter {

	/**
	 * @var WorkspaceDB
	 */
	private WorkspaceDB $workspaceDB;

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @return ConversionDataWriter
	 */
	public static function newFromDatabase( WorkspaceDB $workspaceDB ): ConversionDataWriter {
		return new static(
			$workspaceDB
		);
	}

	/**
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct( WorkspaceDB $workspaceDB ) {
		$this->workspaceDB = $workspaceDB;
	}

	/**
	 * @param string $targetFileName
	 * @param string $newFileContent
	 * @return void
	 */
	public function replaceConfluenceFileContent( string $targetFileName, string $newFileContent ): void {
		if ( isset( $this->confluenceFiles[$targetFileName] ) ) {
			#file_put_contents( $this->confluenceFiles[$targetFileName][0], $newFileContent );
		}
	}
}
