<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use XMLReader;

class Spaces extends ProcessorBase {

	/**
	 * @param WorkspaceDB $workspaceDB
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB,
		private MigrationConfig $migrationConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$spaceId = '';
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$spaceId = $this->getCDATAValue();
				} else {
					$spaceId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$this->process( (int)$spaceId, $properties );
	}

	/**
	 * @param int $spaceId
	 * @param array $properties
	 * @return void
	 */
	private function process( int $spaceId, array $properties ): void {
		if ( $spaceId === -1 ) {
			return;
		}

		$spaceKey = isset( $properties['key'] ) ? $properties['key'] : '';
		$spaceName = isset( $properties['name'] ) ? $properties['name'] : '';
		if ( substr( $spaceKey, 0, 1 ) === '~' ) {
			// User namespaces
			$spaceKey = $this->sanitizeUserSpaceKey( $spaceKey, $spaceName );
			$this->output->writeln( "\033[31mAdd space $spaceKey (ID:$spaceId) - protected user namespace\033[39m" );
		} else {
			$this->output->writeln( "Add space $spaceKey (ID:$spaceId)" );
		}

		// Update property key
		$properties['key'] = $spaceKey;

		// Confluence's GENERAL equals MediaWiki's NS_MAIN, thus having no prefix
		$configSpacePrefix = $this->migrationConfig->getPrefixFromSpaceKeyToPrefixMap( $spaceKey );
		if ( $configSpacePrefix !== null ) {
			$customSpacePrefix = $configSpacePrefix;
		} elseif ( $spaceKey !== 'GENERAL' ) {
			$customSpacePrefix = "{$spaceKey}:";
		} else {
			$customSpacePrefix = '';
		}

		$status = $this->workspaceDB->addSpace(
			$spaceId,
			$spaceKey,
			isset( $properties['name'] ) ? $properties['name'] : null,
			$customSpacePrefix,
			isset( $properties['homePage'] ) ? (int)$properties['homePage'] : null,
			isset( $properties['description'] ) ? (int)$properties['description'] : null
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add space (ID:$spaceId) to the database."
			);
		}
	}

	/**
	 * @param int|string $spaceKey
	 * @param string $spaceName
	 * @return string
	 */
	private function sanitizeUserSpaceKey( $spaceKey, $spaceName ) {
		$spaceKey = substr( $spaceKey, 1, strlen( $spaceKey ) - 1 );
		if ( is_numeric( $spaceKey ) ) {
			$spaceKey = $spaceName;
		}
		$spaceKey = preg_replace( '/[^A-Za-z0-9]/', '', $spaceKey );
		return 'User' . ucfirst( $spaceKey );
	}
}
