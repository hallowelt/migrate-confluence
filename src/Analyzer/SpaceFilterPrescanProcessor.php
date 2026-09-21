<?php

namespace HalloWelt\MigrateConfluence\Analyzer;

use HalloWelt\MigrateConfluence\Analyzer\Processor\ProcessorBase;
use XMLReader;

/**
 * One-shot, read-only pre-scan of entities.xml, run only when
 * `filter-foreign-space-data` is enabled (see doc/configuration.md). Collects
 * the minimal id/property data needed to build a SpaceFilter, without writing
 * anything to the workspace DB.
 *
 * Handles several object classes in one processor (rather than one processor
 * class per class, as the normal Analyzer processors do) because this is an
 * internal, one-off scan, not part of the regular per-entity processor
 * contract.
 */
class SpaceFilterPrescanProcessor extends ProcessorBase {

	/** @var array<int,string> spaceId => spaceKey */
	private array $spaceKeys = [];

	/** @var array<int,int> contentId => spaceId, for Page/BlogPost/PageTemplate/Attachment/SpaceDescription */
	private array $directSpaceOwners = [];

	/** @var array<int,int> commentId => containerContent id (not yet resolved to a space) */
	private array $commentParents = [];

	/** @var array<int,int[]> contentId => labelling ids declared in its "labellings" collection */
	private array $labellingsOf = [];

	/**
	 * @inheritDoc
	 */
	public function doExecute(): void {
		$class = $this->xmlReader->getAttribute( 'class' );

		$id = null;
		$properties = [];
		$collection = [];

		$this->xmlReader->read();
		while ( $this->xmlReader->nodeType !== XMLReader::END_ELEMENT ) {
			if ( $this->xmlReader->name === 'id' ) {
				$id = (int)( $this->xmlReader->nodeType === XMLReader::CDATA
					? $this->getCDATAValue()
					: $this->getTextValue() );
			} elseif ( $this->xmlReader->name === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			} elseif ( $this->xmlReader->name === 'collection' ) {
				// Only "labellings" is needed for the space filter; processCollectionNodes()
				// skips (and XMLReader::next() below discards) any other collection name.
				$collection = $this->processCollectionNodes( $collection, 'labellings' );
			}
			$this->xmlReader->next();
		}

		if ( $id === null ) {
			return;
		}

		switch ( $class ) {
			case 'Space':
				$this->spaceKeys[$id] = $properties['key'] ?? '';
				if ( isset( $properties['description'] ) && $properties['description'] !== '' ) {
					$this->directSpaceOwners[(int)$properties['description']] = $id;
				}
				break;

			case 'Page':
			case 'BlogPost':
			case 'PageTemplate':
			case 'Attachment':
				if ( isset( $properties['space'] ) && $properties['space'] !== '' ) {
					$this->directSpaceOwners[$id] = (int)$properties['space'];
				}
				if ( isset( $collection['labellings'] ) ) {
					$this->labellingsOf[$id] = array_map( 'intval', $collection['labellings'] );
				}
				break;

			case 'SpaceDescription':
				if ( isset( $collection['labellings'] ) ) {
					$this->labellingsOf[$id] = array_map( 'intval', $collection['labellings'] );
				}
				break;

			case 'Comment':
				if ( isset( $properties['containerContent'] ) && $properties['containerContent'] !== '' ) {
					$this->commentParents[$id] = (int)$properties['containerContent'];
				}
				break;
		}
	}

	/**
	 * @return array<int,string>
	 */
	public function getSpaceKeys(): array {
		return $this->spaceKeys;
	}

	/**
	 * @return array<int,int>
	 */
	public function getDirectSpaceOwners(): array {
		return $this->directSpaceOwners;
	}

	/**
	 * @return array<int,int>
	 */
	public function getCommentParents(): array {
		return $this->commentParents;
	}

	/**
	 * @return array<int,int[]>
	 */
	public function getLabellingsOf(): array {
		return $this->labellingsOf;
	}
}
