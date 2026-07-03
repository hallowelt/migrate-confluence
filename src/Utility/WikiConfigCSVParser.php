<?php

namespace HalloWelt\MigrateConfluence\Utility;

class WikiConfigCSVParser {

	/**
	 * @param string $filename
	 * @return array
	 */
	public static function parseWikiConfigCSV( string $filename ): array {
		$wikiConfig = [];

		if ( is_string( $filename ) && is_file( realpath( $filename ) ) ) {
			$resolvedFilename = realpath( $filename );
			$content = file_get_contents( $resolvedFilename );
			if ( $content === false ) {
				return [];
			}

			$lines = explode( "\n", $content );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				$line = trim( $line, ';' );
				if ( $line === '' || str_starts_with( $line, '#' ) ) {
					// Skip empty lines and comments
					continue;
				}

				if ( str_starts_with( $line, 'confluence-space-key' ) ) {
					// Skip the header line
					continue;
				}

				$currentWikiConfig = [
					'space-key' => '',
					'wiki-name' => '',
					'wiki-namespace' => '',
					'wiki-root-page' => '',
				];

				$data = explode( ';', $line );

				if ( isset( $data[0] ) ) {
					$currentWikiConfig['space-key'] = $data[0];
				}
				if ( isset( $data[1] ) ) {
					$currentWikiConfig['wiki-name'] = $data[1];
				}
				if ( isset( $data[2] ) ) {
					$currentWikiConfig['wiki-namespace'] = $data[2];
				}
				if ( isset( $data[3] ) ) {
					$currentWikiConfig['wiki-root-page'] = $data[3];
				}

				$wikiConfig[] = $currentWikiConfig;
			}
		}

		return $wikiConfig;
	}
}
