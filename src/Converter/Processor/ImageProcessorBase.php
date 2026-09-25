<?php

namespace HalloWelt\MigrateConfluence\Converter\Processor;

use DOMDocument;
use DOMNode;
use HalloWelt\MigrateConfluence\Converter\DataWriter\IConverterDataWriter;
use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Utility\ConversionHelper;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\FilenameResolver;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;

/**
 * Shared logic used by both the <ac:image> processor (Image) and the raw
 * HTML <img> processor (RawImage).
 */
abstract class ImageProcessorBase extends ConversionHelper implements IProcessor {

	protected FilenameResolver $filenameResolver;

	public function __construct(
		protected IConverterDataWriter $writer,
		protected DBConversionDataLookup $dataLookup,
		protected int $currentSpaceId,
		protected string $rawPageTitle,
		MigrationConfig $migrationConfig
	) {
		$this->filenameResolver = new FilenameResolver( $dataLookup, $migrationConfig );
	}

	/**
	 * Handle ri:url image inside external link or link in <img>
	 * Replace with a plain text URL so the <a> survives and pandoc renders
	 * [href imageUrl] instead of dropping the link entirely.
	 *
	 * Cleaned external URL (scheme://host/path, query stripped), or '' if not external.
	 */
	protected function getExternalUrlText( string $url ): string {
		$parsed = parse_url( $url );
		if ( !isset( $parsed['scheme'] ) || !isset( $parsed['host'] ) ) {
			return '';
		}
		return $parsed['scheme'] . '://' . $parsed['host'] . ( $parsed['path'] ?? '' );
	}

	protected function getImageReplacement( array $params ): string {
		return '[[File:' . implode( '|', $params ) . ']]';
	}

	protected function makeImageLinkWithDebugInfo( DOMDocument $dom, array $params,
		string $confluenceFileKey, string $debug = '' ): DOMNode {
		$params = array_map( 'trim', $params );

		if ( empty( $params ) || empty( $params[0] ) ) {
			$debug .= " ###BROKENIMAGE $confluenceFileKey ###";
		}

		$replacementText = $this->getImageReplacement( $params );
		$replacementText .= $debug;

		return $this->createTextNode( $dom, $replacementText, __METHOD__ );
	}

}
