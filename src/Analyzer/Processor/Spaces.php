<?php

namespace HalloWelt\MigrateConfluence\Analyzer\Processor;

use DOMDocument;
use DOMElement;
use HalloWelt\MigrateConfluence\Utility\XMLHelper;

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
	public function doExecute( DOMDocument $dom ): void {
		$this->xmlHelper = new XMLHelper( $dom );

		$objectNodes = $this->xmlHelper->getObjectNodes( 'Space' );
		if ( count( $objectNodes ) < 1 ) {
			return;
		}
		$objectNode = $objectNodes->item( 0 );
		if ( $objectNode instanceof DOMElement === false ) {
			return;
		}

		$this->process( $objectNode );
	}

	/**
	 * @param DOMElement $node
	 * @return void
	 */
	private function process( DOMElement $node ): void {
		$spaceId = $this->xmlHelper->getIDNodeValue( $node );
		if ( $spaceId === -1 ) {
			return;
		}

		$spaceKey = $this->xmlHelper->getPropertyValue( 'key', $node );
		$spaceName = $this->xmlHelper->getPropertyValue( 'name', $node );
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

		$homePageId = -1;
		$homePagePropertyNode = $this->xmlHelper->getPropertyNode( 'homePage', $node );
		if ( $homePagePropertyNode !== null ) {
			$homePageId = $this->xmlHelper->getIDNodeValue( $homePagePropertyNode );
		}
		if ( $homePageId > -1 ) {
			//$this->buckets->addData( 'global-space-id-homepages', $spaceId, $homePageId, false, true );
			$this->data['global-space-id-homepages'][$spaceId] = $homePageId;
		}

		$details = [];
		// Property id
		$details['id'] = $spaceId;

		// Property key
		$details['key'] = $spaceKey;

		// Text only propterties
		$properties = [
			'name', 'creationDate', 'lastModificationDate', 'spaceType', 'spaceStatus'
		];

		foreach ( $properties as $property ) {
			$details[$property] = $this->xmlHelper->getPropertyValue( $property, $node );
		}

		// ID (int) node propterties
		$propertyNode = $this->xmlHelper->getPropertyNode( 'description' );
		if ( $propertyNode !== null ) {
			$details['description'] = $this->xmlHelper->getIDNodeValue( $propertyNode );
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

		$propertyNode = $this->xmlHelper->getPropertyNode( 'homePage' );
		if ( $propertyNode !== null ) {
			$details['homePage'] = $this->xmlHelper->getIDNodeValue( $propertyNode );
		}

		// ID (key) node propterties
		$properties = [
			'creator', 'lastModifier'
		];

		foreach ( $properties as $property ) {
			$propertyNode = $this->xmlHelper->getPropertyNode( $property );
			if ( $propertyNode !== null ) {
				$details[$property] = $this->xmlHelper->getKeyNodeValue( $propertyNode );
			}
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
