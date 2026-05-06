<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
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
	 */
	public function __construct(
		private WorkspaceDB $workspaceDB
	) {}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$descriptionId = '';
		$properties = [];
		$collection = [];
		$bodyContents = [];
		$labellings = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$descriptionId = $this->getCDATAValue();
				} else {
					$descriptionId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( strtolower( $this->xmlReader->name ) === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}

			$this->xmlReader->next();
		}

		if ( isset( $collection['bodyContents'] ) ) {
			$bodyContents = $collection['bodyContents'];
		}
		if ( isset( $collection['labellings'] ) ) {
			$labellings = $collection['labellings'];
		}

		$status = $this->workspaceDB->addSpaceDescription(
			(int)$descriptionId,
			$bodyContents,
			$labellings
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
