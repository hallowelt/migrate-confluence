<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use HalloWelt\MigrateConfluence\Database\ConfigDB;
use HalloWelt\MigrateConfluence\Database\WorkspaceDB;
use XMLReader;

class BodyContents extends ProcessorBase {

	/**
	 * @param ConfigDB $configDB
	 * @param WorkspaceDB $workspaceDB
	 */
	public function __construct(
		private ConfigDB $configDB,
		private WorkspaceDB $workspaceDB
	) {}

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$bodyContentId = -1;
		$properties = [];
		$contentClass = '';

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( strtolower( $this->xmlReader->name ) === 'id' ) {
				if ( $this->xmlReader->nodeType === XMLReader::CDATA ) {
					$bodyContentId = (int)$this->getCDATAValue();
				} else {
					$bodyContentId = (int)$this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'content' ) {
					$contentClass = $this->xmlReader->getAttribute( 'class' ) ?? '';
				}
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		if ( !isset( $properties['content'] ) ) {
			return;
		}

		// The body will be extracted later as file for pandoc and does not need to be in database.
		// We store it in a separate table to be able to easily retrieve it for the content transformation and
		// to keep the main table smaller.
		if ( isset( $properties['body'] ) ) {
			$this->workspaceDB->addBodyContentBody(
				$bodyContentId,
				$properties['body']
			);
			unset( $properties['body'] );
		}

		$contentId = (int)trim( $properties['content'] );

		$status = $this->workspaceDB->addBodyContent(
			$bodyContentId,
			$contentId,
			$contentClass,
			$properties
		);
	}

}
