<?php

namespace HalloWelt\MigrateConfluence\Utility;

class UsermapOptionHelper {

	/** @var array */
	private array $usermap = [];

	public function __construct( private ?string $usermapFilePath ) {
	}

	public function validateFile(): ?string {
		$filename = $this->usermapFilePath;

		if ( $filename === null ) {
			return null;
		}

		$resolvedFilename = realpath( $filename );
		if ( $resolvedFilename === false || !is_file( $resolvedFilename ) ) {
			return "Usermap file '$filename' does not exist.";
		}

		$usermap = $this->parseUsermapCSV( $resolvedFilename );
		if ( $usermap === [] ) {
			return "Usermap file '$filename' is empty or could not be read.";
		}

		foreach ( $usermap as $mapping ) {
			if ( $mapping['confluence-username'] === '' ) {
				return "Usermap file '$filename' is missing 'confluence-username' for a row.";
			}
			if ( $mapping['wiki-username'] === '' ) {
				return "Usermap file '$filename' is missing 'wiki-username' for a row.";
			}
		}
		$this->usermap = $usermap;

		// No validation errors
		return null;
	}

	public function getConfig(): array {
		return $this->usermap;
	}

	/**
	 * @param string $resolvedFilename
	 *
	 * @return array
	 */
	private function parseUsermapCSV( string $resolvedFilename ): array {
		$usermap = [];

		$file = new \SplFileObject( $resolvedFilename, 'r' );
		$file->setFlags( \SplFileObject::READ_CSV );

		foreach ( $file as $data ) {
			$mapping = $this->makeMapping( $data );
			if ( $mapping === null ) {
				continue;
			}
			$usermap[] = $mapping;
		}

		return $usermap;
	}

	/**
	 * @param array $data A single CSV row
	 *
	 * @return array|null Mapping, or null if the row should be skipped
	 */
	private function makeMapping( array $data ): ?array {
		$confluenceUsername = trim( $data[0] ?? '' );
		if ( $confluenceUsername === '' || str_starts_with( $confluenceUsername, '#' ) ) {
			// Skip empty lines and comments
			return null;
		}
		if ( str_starts_with( strtolower( $confluenceUsername ), 'confluence-username' ) ) {
			// Skip the header line
			return null;
		}

		// Confluence usernames are matched case-insensitively against
		// the `lowerName` property from entities.xml.
		return [
			'confluence-username' => strtolower( $confluenceUsername ),
			'wiki-username' => trim( $data[1] ?? '' ),
		];
	}
}
