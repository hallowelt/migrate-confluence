<?php

namespace HalloWelt\MigrateConfluence\Utility;

class WikiConfigOptionHelper {
	public function __construct( private ?string $configFilePath ) {
	}

	/**
	 * @return string|null
	 */
	public function validateFile(): ?string {
		$filename = $this->configFilePath;

		if ( $filename === null ) {
			return null;
		}

		if ( !is_file( realpath( $filename ) ) ) {
			return "Config file '$filename' does not exist.";
		}

		$content = file_get_contents( realpath( $filename ) );
		if ( !$content ) {
			return "Config file '$filename' is empty or could not be read.";
		}

		$wikiConfig = WikiConfigCSVParser::parseWikiConfigCSV( $filename );
		foreach ( $wikiConfig as $config ) {
			if ( !isset( $config['confluence-space-key'] ) || empty( $config['confluence-space-key'] ) ) {
				return "Config file '$filename' is missing 'confluence-space-key' for a space.";
			}
			if ( !isset( $config['wiki-name'] ) || empty( $config['wiki-name'] ) ) {
				return "Config file '$filename' is missing 'wiki-name' for a space.";
			}
			if ( !isset( $config['wiki-namespace'] ) ) {
				return "Config file '$filename' is missing 'wiki-namespace' for a space.";
			}
			if ( !isset( $config['wiki-root-page'] ) ) {
				return "Config file '$filename' is missing 'wiki-root-page' for a space.";
			}
		}
		$this->wikiConfig = $wikiConfig;

		// No validation errors
		return null;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array {
		return $this->wikiConfig;
	}

	/**
	 * @return void
	 */
	public function showConfig(): void {
		if ( $this->wikiConfig === [] ) {
			$error = $this->validateFile();
			if ( $error !== null ) {
				echo $error . PHP_EOL;
				return;
			}
		}

		echo 'Loaded config:' . PHP_EOL;
		var_dump( $this->wikiConfig );
	}
}

}