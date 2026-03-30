<?php

namespace HalloWelt\MigrateConfluence\Utility;

use HalloWelt\MediaWiki\Lib\Migration\InvalidTitleException;
use HalloWelt\MediaWiki\Lib\Migration\TitleBuilder as GenericTitleBuilder;
use HalloWelt\MediaWiki\Lib\Migration\WindowsFilename;

class FilenameBuilder {

	/**
	 * @var array
	 */
	private array $config;

	/**
	 * @var array
	 */
	private array $spaceIdPrefixMap;

	/**
	 * @param array $spaceIdPrefixMap
	 * @param array $config
	 */
	public function __construct( array $spaceIdPrefixMap, array $config = [] ) {
		$this->spaceIdPrefixMap = $spaceIdPrefixMap;
		$this->config = $config;
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
			$assocTitle = str_replace( '/', '_', $assocTitle );
			// Unset potential namespace prefix to avoid duplications
			$builder->setNamespace( 0 );
			$builder->appendTitleSegment( "-{$originalFilename}" );
			$builder->appendTitleSegment( $assocTitle );
		} else {
			$builder->appendTitleSegment( "{$originalFilename}" );
		}
		$builtTitle = $builder->invertTitleSegments()->build();

		$filename = new WindowsFilename( $builtTitle );
		$filename = (string)$filename;

		if (
			isset( $this->config['ext-ns-file-repo-compat'] )
			&& $this->config['ext-ns-file-repo-compat'] === true
		) {
			$filePrefix = $this->spaceIdPrefixMap[$spaceId];
			if ( $filePrefix !== '' ) {
				$namespacePart = substr( $filePrefix, 0, strpos( $filePrefix, ':' ) );
				if ( strpos( $filename, "{$namespacePart}_" ) === 0 ) {
					$filename = "{$namespacePart}:" . substr( $filename, strlen( "{$namespacePart}_" ) );
				}
			}
		}

		return $filename;
	}
}
