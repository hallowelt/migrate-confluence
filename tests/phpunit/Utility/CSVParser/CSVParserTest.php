<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\CSVParser;

use HalloWelt\MigrateConfluence\Utility\CSVParser;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class CSVParserTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CSVParser::parseCSV()
	 */
	public function testTrailingNewlineDoesNotProduceEmptyRecord(): void {
		$this->assertParsedRowCount( "a,b\nc,d\n", 2 );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CSVParser::parseCSV()
	 */
	public function testWithoutTrailingNewlineProducesSameRecordCount(): void {
		$this->assertParsedRowCount( "a,b\nc,d", 2 );
	}

	/**
	 * @param string $content
	 * @param int $expectedCount
	 */
	private function assertParsedRowCount( string $content, int $expectedCount ): void {
		$tmpPath = tempnam( sys_get_temp_dir(), 'csv-parser-' );
		file_put_contents( $tmpPath, $content );

		$parser = new CSVParser(
			$tmpPath,
			static function ( array $data, int $rowNumber ) {
				return $data;
			},
			new MigrationConfig( [] )
		);

		$error = '';
		$isValid = $parser->validateFile( $error );
		unlink( $tmpPath );

		$this->assertTrue( $isValid, $error );
		$this->assertCount( $expectedCount, $parser->getRecords() );
	}
}
