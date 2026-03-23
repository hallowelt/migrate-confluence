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
			'global-space-id-to-key-map',
			'global-space-id-to-prefix-map',
			'global-space-id-homepages',
			'global-space-id-to-description-id-map',
			'global-space-details',
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
		if ( isset( $this->spacePrefixMap[$spaceKey] ) ) {
			$customSpacePrefix = $this->spacePrefixMap[$spaceKey];
		} elseif ( $spaceKey !== 'GENERAL' ) {
			$customSpacePrefix = "{$spaceKey}:";
		} else {
			$customSpacePrefix = '';
		}

		$this->data['global-space-id-to-key-map'][$spaceId] = $spaceKey;
		$this->data['global-space-id-to-prefix-map'][$spaceId] = $customSpacePrefix;

		$homePageId = isset( $properties['homePage'] ) ? (int)$properties['homePage'] : -1;
		if ( $homePageId > -1 ) {
			$this->data['global-space-id-homepages'][$spaceId] = $homePageId;
		}

		// Property id
		$properties['id'] = $spaceId;

		// ID (int) node propterties
		if ( isset( $properties['description'] ) ) {
			$this->data['global-space-id-to-description-id-map'][$spaceId] = (int)$properties['description'];

			$this->output->writeln( "Add space description ($spaceId)" );
		}

		if ( !empty( $properties ) ) {
			$this->data['global-space-details'][$spaceId] = $properties;
			$this->output->writeln( "Add space details ($spaceId)" );
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
