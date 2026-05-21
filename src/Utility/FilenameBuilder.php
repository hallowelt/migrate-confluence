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
	public function buildFromAttachmentData(
		int $spaceId, string $originalFilename, string $assocTitle, string $suffix = ''
	): string {
		$builder = new GenericTitleBuilder( $this->spaceIdPrefixMap );
		$builder->setNamespace( $spaceId );

		$originalFilename = str_replace( [ ' ', '/' ], '_', $originalFilename );

		if ( !empty( $suffix ) ) {
			$suffix = str_replace( [ ' ', '/' ], '_', $suffix );
			$originalFilenameParts = explode( '.', $originalFilename );
			if ( count( $originalFilenameParts ) > 1 ) {
				$extension = array_pop( $originalFilenameParts );
				$filenameWithoutExtension = implode( '.', $originalFilenameParts );
				$filenameWithoutExtension .= $suffix;
				$originalFilename = $filenameWithoutExtension . '.' . $extension;
			} else {
				$originalFilename .= $suffix;
			}
		}

		$builder->appendTitleSegment( $originalFilename );

		if ( !empty( $assocTitle ) ) {
			$assocTitle = str_replace( [ ' ', '/' ], '_', $assocTitle );
			$builder->appendTitleSegment( "$assocTitle-" );
		}

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
