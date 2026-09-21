<?php

namespace HalloWelt\MigrateConfluence\Utility;

class CSVParser {

	private array $records = [];

	public function __construct(
		private string $csvFilePath,
		private \Closure $recordHandler,
		private MigrationConfig $migrationConfig ) {
	}

	public function validateFile( string &$error ): bool {
		$filename = $this->csvFilePath;

		$resolvedFilename = realpath( $filename );
		if ( $resolvedFilename === false || !is_file( $resolvedFilename ) ) {
			$error = "CSV file '$filename' does not exist.";
			return false;
		}

		try {
			$this->parseCSV( $resolvedFilename );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}
		if ( !empty( $error ) ) {
			/* $recordHandler filled the $error variable */
			return false;
		}
		if ( !count( $this->records ) ) {
			$error = "CSV file '$filename' is empty or could not be read.";
			return false;
		}

		return true;
	}

	public function getRecords(): array {
		return $this->records;
	}

	/**
	 * @param string $resolvedFilename
	 */
	private function parseCSV( string $resolvedFilename ): void {
		$file = new \SplFileObject( $resolvedFilename, 'r' );
		$file->setFlags( \SplFileObject::READ_CSV );
		$file->setCsvControl(
			$this->migrationConfig->getCSVSeparator(),
			'"',
			# empty string is recommended by <https://www.php.net/splfileobject.setcsvcontrol>:
			''
		);

		$rowNumber = -1;
		foreach ( $file as $data ) {
			$rowNumber++;
			if ( !count( $data ) || $data === [ null ] || str_starts_with( trim( $data[0] ?? '' ), '#' ) ) {
				// Skip empty lines (including an empty row following a trailing newline) and comments
				continue;
			}
			$mapping = ( $this->recordHandler )( $data, $rowNumber );
			if ( $mapping === null ) {
				continue;
			}
			$this->records[] = $mapping;
		}
	}
}
