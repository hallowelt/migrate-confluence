<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Exception;

class WikisOptionCSVParser {

	/**
	 * @param string $filename
	 *
	 * @return array
	 * @throws Exception
	 */
	public function parseWikiConfigCSV( string $filename ): array {
		$wikiConfig = [];

		if ( is_file( realpath( $filename ) ) ) {
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
					$currentWikiConfig['wiki-name'] = $this->sanitizeWikiName( $data[1] );
				}
				if ( isset( $data[2] ) ) {
					$currentWikiConfig['wiki-namespace'] = $this->sanitizeWikiNamespace( $data[2] );
				}
				if ( isset( $data[3] ) ) {
					$currentWikiConfig['wiki-root-page'] = $this->sanitizeWikiRootPage( $data[3] );
				}

				$wikiConfig[] = $currentWikiConfig;
			}
		}

		return $wikiConfig;
	}

	/**
	 * @param string $name
	 * @return string
	 */
	private function sanitizeWikiName( string $name ): string {
		$name = $this->genericSanitize( $name );
		return $name;
	}

	/**
	 * @param string $namespace
	 * @return string
	 * @throws Exception if the namespace starts with a digit
	 */
	private function sanitizeWikiNamespace( string $namespace ): string {
		$namespace = $this->genericSanitize( $namespace );
		if ( preg_match( '#^\d#', $namespace ) ) {
			throw new Exception(
				"Wiki namespace must not start with a digit: '$namespace'"
			);
		}
		return $namespace;
	}

	/**
	 * @param string $title
	 * @return string
	 */
	private function sanitizeWikiRootPage( string $title ): string {
		$title = trim( $title );
		return $title;
	}

	/**
	 * @param string $text
	 * @return string
	 */
	private function genericSanitize( string $text ): string {
		$text = trim( $text );
		$text = str_replace(
			[ ' ', ':', ';', ',', '#', '+', '?', '*', '~', '"', "'" ],
			'_',
			$text
		);
		$text = preg_replace( '#_+#', '_', $text );
		return $text;
	}
}
