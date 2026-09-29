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

	private const IMAGE_EXTENSION_FALLBACK = '.jpg';

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
	 * Ensures compatibility with mediawikis handling of external image urls.
	 *
	 *  Handle ri:url image inside external link or link in <img>
	 *  Replace with a plain text URL so the <a> survives and pandoc renders
	 *  [href imageUrl] instead of dropping the link entirely.
	 *
	 * 	HACK: If the URL has query params, they are kept and a file extension is appended
	 * 	as a fragment at the end of the string, so MediaWiki still recognizes the file type.
	 * 	Default extension if none is present: .jpg
	 * 	e.g.
	 * 	https://example.com/download/attachments/1933361/image.png?version=1&modificationDate=1724938529723
	 * 	->
	 * 	https://example.com/download/attachments/1933361/image.png?version=1&modificationDate=1724938529723#.png
	 *
	 * Cleaned external image URL (scheme://host/path[?query#.ext]), or '' if not external.
	 */
	protected function modifyExternalImageUrl( string $url ): string {
		$parsed = parse_url( $url );
		if ( !isset( $parsed['scheme'] ) || !isset( $parsed['host'] ) ) {
			return '';
		}

		$path = $parsed['path'] ?? '';
		$result = $parsed['scheme'] . '://' . $parsed['host'] . $path;

		if ( isset( $parsed['query'] ) && $parsed['query'] !== '' ) {
			$extension = pathinfo( $path, PATHINFO_EXTENSION );
			if ( $extension === '' ) {
				$extension = self::IMAGE_EXTENSION_FALLBACK;
			}
			$result .= '?' . $parsed['query'] . '#.' . $extension;
		}

		return $result;
	}



}
