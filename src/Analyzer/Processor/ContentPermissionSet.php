<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MediaWiki\Lib\Migration\Logging\Log;
use HalloWelt\MigrateConfluence\Analyzer\DataWriter\IAnalyzeDataWriter;
use XMLReader;

/**
 * A page restriction list: one set per restricted operation ("View" or "Edit") of a page or blog post.
 *
 * <object class="ContentPermissionSet" package="com.atlassian.confluence.security">
 * 	<id name="id">123</id>
 * 	<property name="owningContent" class="Page" package="com.atlassian.confluence.pages">
 * 		<id name="id">456</id>
 * 	</property>
 * 	<property name="type"><![CDATA[Edit]]></property>
 * 	<collection name="contentPermissions" class="java.util.HashSet">
 * 		<element class="ContentPermission" package="com.atlassian.confluence.security">
 * 			<id name="id">789</id>
 * 		</element>
 * 	</collection>
 * </object>
 */
class ContentPermissionSet extends ProcessorBase {

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
		$setId = -1;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$setId = (int)$this->getCDATAValue();
				} else {
					$setId = (int)$this->getTextValue();
				}
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				$collection = $this->processCollectionNodes( $collection );
			}
			$this->xmlReader->next();
		}

		$contentId = (int)( $properties['owningContent'] ?? 0 );
		$type = (string)( $properties['type'] ?? '' );
		$permissionIds = array_map( 'intval', $collection['contentPermissions'] ?? [] );

		if ( $contentId <= 0 || $type === '' ) {
			// Kept in the DB anyway: the extractor must not treat the content as unrestricted unnoticed
			Log::to( 'db' )->warning(
				"Permission set $setId has no owning content or no type (content: '$contentId', type: '$type')."
			);
		}

		$status = $this->writer->addContentPermissionSet(
			$setId,
			$contentId,
			$type,
			$permissionIds,
			$properties
		);

		if ( !$status ) {
			$xmlFile = $this->xmlReader->baseURI;
			Log::to( 'db' )->critical(
				"Permission set ID $setId already exists in the database."
					. " Source directory: '" . dirname( $xmlFile ) . "', file: '" . basename( $xmlFile ) . "'."
			);
		}

		Log::to( 'console' )->debug( "Add content permission set (ID:$setId)" );
	}
}
