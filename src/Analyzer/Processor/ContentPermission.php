<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

/**
 * One user or group a page restriction applies to.
 *
 * <object class="ContentPermission" package="com.atlassian.confluence.security">
 * 	<id name="id">789</id>
 * 	<property name="type"><![CDATA[Edit]]></property>
 * 	<property name="group"><![CDATA[developers]]></property>
 * 	<property name="userSubject" class="ConfluenceUserImpl" package="com.atlassian.confluence.user">
 * 		<id name="key"><![CDATA[12345]]></id>
 * 	</property>
 * 	<property name="owningSet" class="ContentPermissionSet" package="com.atlassian.confluence.security">
 * 		<id name="id">123</id>
 * 	</property>
 * 	<property name="lastModificationDate">2026-03-09 13:06:33.928</property>
 * </object>
 */
class ContentPermission extends ProcessorBase {

	/**
	 * @param IAnalyzeDataWriter $writer
	 */
	public function __construct(
		private IAnalyzeDataWriter $writer
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$permissionId = -1;
		$properties = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$permissionId = (int)$this->getCDATAValue();
				} else {
					$permissionId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		// The group is called "group" in SpacePermission; accept both names for the content permission
		$groupName = (string)( $properties['groupName'] ?? $properties['group'] ?? '' );
		$userKey = (string)( $properties['userSubject'] ?? '' );
		$type = (string)( $properties['type'] ?? '' );
		$setId = (int)( $properties['owningSet'] ?? 0 );

		if ( $groupName === '' && $userKey === '' ) {
			// Stored anyway: the restriction list stays restrictive, it just names nobody
			Log::to( 'db' )->warning(
				"Content permission $permissionId names neither a user nor a group."
			);
		}

		$timestamp = $this->buildTimestamp(
			(string)( $properties['lastModificationDate'] ?? $properties['creationDate'] ?? '' )
		);

		$status = $this->writer->addContentPermission(
			$permissionId,
			$setId,
			$type,
			$groupName,
			$userKey,
			$timestamp,
			$properties
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			Log::to( 'db' )->critical(
				"Content permission ID $permissionId already exists in the database."
					. " Source directory: '" . dirname( $xmlFile ) . "', file: '" . basename( $xmlFile ) . "'."
			);
		}

		Log::to( 'console' )->debug( "Add content permission (ID:$permissionId)" );
	}
}
