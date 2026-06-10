<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use XMLReader;

/**
 * <object class="SpaceDescription" package="com.atlassian.confluence.spaces">
 * 	<id name="id">456789</id>
 * 	<property name="hibernateVersion">0</property>
 * 	<property name="title"/>
 * 	<property name="lowerTitle"/>
 * 	<collection name="bodyContents" ...>
 * 		<element class="BodyContent" package="com.atlassian.confluence.core">
 * 			<id name="id">987654</id>
 * 		</element>
 * 	</collection>
 * 	<property name="version">2</property>
 * 	<property name="creationDate">2013-05-10 14:33:31.000</property>
 * 	<property name="lastModificationDate">2013-05-10 14:36:36.000</property>
 * 	<property name="versionComment"><![CDATA[]]></property>
 * 	<property name="originalVersion" class="SpaceDescription"><id name="id">1234567</id>
 * 	</property>
 * 	<property name="originalVersionId">1234567</property>
 * 	<property name="contentStatus"><![CDATA[current]]></property>
 * 	<property name="navigationType">0</property>
 * 	<property name="entitySubType"/>
 * </object>
 */
class SpaceDescription extends ProcessorBase {

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
		$descriptionId = '';
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$descriptionId = $this->getCDATAValue();
				} else {
					$descriptionId = $this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}

			$this->xmlReader->next();
		}

		$bodyContentIds = [];
		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContentIds = $collection['bodyContents'];
		}
		// A fallback mechanism for body content IDs in case they are not found in the collection
		// is placed in the ConfluenceAnalyzer, which will attempt to retrieve them from the
		// body_contents table based on the space description ID.

		$labellingsIds = [];
		if ( isset( $collection['labellings'] ) ) {
			$labellingsIds = $collection['labellings'];
		}

		$version = '';
		if ( isset( $properties['version'] ) ) {
			$version = $properties['version'];
		}

		$lastModificationDate = '';
		if ( isset( $properties['lastModificationDate'] ) ) {
			$lastModificationDate = $properties['lastModificationDate'];
		}

		$revisionTimestamp = $this->buildTimestamp( $lastModificationDate );

		$originalVersionId = -1;
		if ( isset( $properties['originalVersion'] ) ) {
			$originalVersionId = (int)$properties['originalVersion'];
		}

		$contentStatus = null;
		if ( isset( $properties['contentStatus'] ) ) {
			$contentStatus = $properties['contentStatus'];
		}

		if ( !$this->migrationConfig->getIncludeHistory() && $originalVersionId > 0 ) {
			return;
		}

		$status = $this->workspaceDB->addSpaceDescription(
			(int)$descriptionId,
			$revisionTimestamp,
			$version,
			$originalVersionId,
			$bodyContentIds,
			$labellingsIds,
			$properties,
			$collection
		);

		if ( !$status ) {
			$this->workspaceDB->addLogEntry(
				'error',
				'analyze',
				__CLASS__,
				"Failed to add space description (ID:$descriptionId) to the database."
			);
		}

		$this->output->writeln( "\nAdd space description ($descriptionId)" );
	}
}
