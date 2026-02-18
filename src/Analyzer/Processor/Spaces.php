<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use XMLReader;

class Spaces extends ProcessorBase {

	/** @var XMLHelper */
	protected $xmlHelper;

	/** @var array */
	protected $spacePrefixMap = [];

	/**
	 * @param array $spacePrefixMap
	 */
	public function __construct( array $spacePrefixMap ) {
		$this->spacePrefixMap = $spacePrefixMap;
	}

	/**
	 * @inheritDoc
	 */
	public function getKeys(): array {
		return [
			'global-space-id-to-prefix-map',
			'global-space-key-to-prefix-map',
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-space-details',

			'analyze-space-id-to-space-key-map',
			'analyze-space-name-to-prefix-map',
			'analyze-space-id-to-name-map',
			'analyze-space-key-to-name-map',
		];
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
				$name = $this->xmlReader->getAttribute( 'name' );
				if ( $name === 'key' ) {
					$spaceId = $this->getCDATAValue();
				} else {
					$spaceId = $this->getTextValue();
				}
			} elseif ( strtolower( $this->xmlReader->name ) === 'property' ) {
				$properties = $this->processPropertyNodes( $properties );
			}
			$this->xmlReader->next();
		}

		$this->process( $spaceId, $properties );
	}

	/**
	 * @param int|string $id
	 * @param array $properties
	 * @return void
	 */
	private function process( $spaceId, array $properties ): void {
		if ( $spaceId === -1 ) {
			return;
		}

		$spaceKey = isset( $properties['key'] )? $properties['key'] : '';
		$spaceName = isset( $properties['key'] )? $properties['name'] : '';
		if ( substr( $spaceKey, 0, 1 ) === '~' ) {
			// User namespaces
			$spaceKey = $this->sanitizeUserSpaceKey( $spaceKey, $spaceName );
			$this->output->writeln( "\033[31mAdd space $spaceKey (ID:$spaceId) - protected user namespace\033[39m" );
		} else {
			$this->output->writeln( "Add space $spaceKey (ID:$spaceId)" );
		}

		// Confluence's GENERAL equals MediaWiki's NS_MAIN, thus having no prefix
		if ( $spaceKey === 'GENERAL' ) {
			$spaceKey = '';
		}

		// Update property key
        $details['key'] = $spaceKey;

		if ( isset( $this->spacePrefixMap[$spaceKey] ) ) {
			$customSpacePrefix = $this->spacePrefixMap[$spaceKey];
		} elseif ( $spaceKey !== '' ) {
			$customSpacePrefix = "{$spaceKey}:";
		} else {
			return;
		}

		/*
		$this->buckets->addData(
			'global-space-id-to-prefix-map', $spaceId, $customSpacePrefix, false, true
		);
		$this->buckets->addData(
			'global-space-key-to-prefix-map', $spaceKey, $customSpacePrefix, false, true
		);
		$this->customBuckets->addData(
			'analyze-space-id-to-space-key-map', $spaceId, $spaceKey, false, true
		);
		$this->customBuckets->addData(
			'analyze-space-name-to-prefix-map', $spaceName, $customSpacePrefix, false, true
		);
		$this->customBuckets->addData(
			'analyze-space-id-to-name-map', $spaceId, $spaceName, false, true
		);
		$this->customBuckets->addData(
			'analyze-space-key-to-name-map', $spaceKey, $spaceName, false, true
		);
		*/

		$this->data['global-space-id-to-prefix-map'][$spaceId] = $customSpacePrefix;
		$this->data['global-space-key-to-prefix-map'][$spaceKey] = $customSpacePrefix;

		$this->data['analyze-space-id-to-space-key-map'][$spaceId] = $spaceKey;
		$this->data['analyze-space-name-to-prefix-map'][$spaceName] = $customSpacePrefix;
		$this->data['analyze-space-id-to-name-map'][$spaceId] = $spaceName;
		$this->data['analyze-space-key-to-name-map'][$spaceKey] = $spaceName;

		$homePageId = isset( $properties['homePage'] )? $properties['homePage'] : -1;

		if ( $homePageId > -1 ) {
			//$this->buckets->addData( 'global-space-id-homepages', $spaceId, $homePageId, false, true );
			$this->data['global-space-id-homepages'][$spaceId] = $homePageId;
		}

		// Property id
		$properties['id'] = $spaceId;

		// ID (int) node propterties
		if ( isset( $details['description'] ) ) {
			/*
			$this->buckets->addData(
				'global-space-id-to-description-id-map',
				$spaceId,
				$details['description'],
				false,
				true
			);
			*/
			$this->data['global-space-id-to-description-id-map'][$spaceId] = $details['description'];

			$this->output->writeln( "Add space description ($spaceId)" );
		}

		if ( !empty( $details ) ) {
			//$this->buckets->addData( 'global-space-details', $spaceId, $details, false, true );
			$this->data['global-space-details'][$spaceId] = $details;
			$this->output->writeln( "Add details description ($spaceId)" );
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
