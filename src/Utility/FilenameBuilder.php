<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;

class FilenameBuilder {

	/**
	 * @var MigrationConfig
	 */
	private MigrationConfig $migrationConfig;

	/**
	 * @var array
	 */
	private array $spaceIdPrefixMap;

	/**
	 * @param array $spaceIdPrefixMap
	 * @param MigrationConfig $migrationConfig
	 */
	public function __construct( array $spaceIdPrefixMap, MigrationConfig $migrationConfig ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->migrationConfig = $migrationConfig;
	}

	/**
	 * @param int $spaceId
	 * @param string $originalFilename
	 * @param string $assocTitle
	 *
	 * @return string
	 * @throws InvalidTitleException
	 */
	public function buildFromAttachmentData( int $spaceId, string $originalFilename, string $assocTitle ): string {
		$builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$builder->setNamespace( $spaceId );

		if ( !empty( $assocTitle ) ) {
			// Namespace is already set with assocTitle
			$builder->setNamespace( 0 );

			$assocTitle = str_replace( '/', '_', $assocTitle );
			$filenameParts = explode( '.', $originalFilename );
			if ( count( $filenameParts ) > 1 ) {
				$originalFilename = $assocTitle . '_' . implode( '.', $filenameParts );
			} else {
				$originalFilename = implode( '.', $filenameParts ) . $assocTitle;
			}
		}
		$builder->appendTitleSegment( "$originalFilename" );

		$builtTitle = $builder->invertTitleSegments()->build();

		$filename = new WindowsFilename( $builtTitle );
		$filename = (string)$filename;

		if ( $this->migrationConfig->getExtNsFileRepoCompat() === true ) {
			if ( isset( $this->spaceIdPrefixMap[$spaceId] ) ) {
				$filePrefix = $this->spaceIdPrefixMap[$spaceId];
				if ( $filePrefix !== '' ) {
					$namespacePart = substr( $filePrefix, 0, strpos( $filePrefix, ':' ) );
					if ( strpos( $filename, "{$namespacePart}_" ) === 0 ) {
						$filename = "$namespacePart:" . substr( $filename, strlen( "{$namespacePart}_" ) );
					}
				}
			}
		}

		return $filename;
	}
}
