<?php

namespace HalloWelt\MigrateConfluence\Utility;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigOptionHelper {

	/** @var array */
	private array $advancedConfig = [];

	public function __construct( private ?string $configFilePath ) {
	}

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

		try {
			$config = Yaml::parse( $content );
		} catch ( ParseException $e ) {
			return "Config file '$filename' is not a valid YAML file: " . $e->getMessage();
		}

		if ( !is_array( $config ) || !isset( $config['config'] ) || !is_array( $config['config'] ) ) {
			return "Config file '$filename' must contain a 'config' key with an array of configuration options.";
		}

		$this->advancedConfig = $config['config'];

		// No validation errors
		return null;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array {
		return $this->advancedConfig;
	}

	/**
	 * @return void
	 */
	public function showConfig(): void {
		if ( $this->advancedConfig === [] ) {
			$error = $this->validateFile();
			if ( $error !== null ) {
				echo $error . PHP_EOL;
				return;
			}
		}

		echo 'Loaded config:' . PHP_EOL;
		var_dump( $this->advancedConfig );
	}
}
