<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\CQLParser;

use HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser;
use HalloWelt\MigrateConfluence\Utility\CQLParser\DplCQLParser;
use PHPUnit\Framework\TestCase;

class DplCQLParserTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\DplCQLParser::buildQueryFromClauses()
	 */
	public function testDplParserExtendsBaseParser(): void {
		$this->assertTrue( is_subclass_of( DplCQLParser::class, CQLParser::class ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testLabelClausesAreMappedToDplParameters(): void {
		$this->assertParsedResult( 'label = "dev"', 'category=Dev' );
		$this->assertParsedResult( 'label != "dev"', 'notcategory=Dev' );
		$this->assertParsedResult( 'label in ("dev", "test")', 'category=Dev{{!}}Test' );
		$this->assertParsedResult( 'label not in ("dev", "test")', "notcategory=Dev\nnotcategory=Test" );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testSpaceClausesAreMappedToDplParameters(): void {
		$this->assertParsedResult( 'space = "DEV"', 'namespace=DEV' );
		$this->assertParsedResult( 'space != "DEV"', 'notnamespace=DEV' );
		$this->assertParsedResult( 'space in ("DEV", "Test")', 'namespace=DEV{{!}}Test' );
		$this->assertParsedResult( 'space not in ("DEV", "Test")', "notnamespace=DEV\nnotnamespace=Test" );
		$this->assertParsedResult( 'space = currentSpace()', 'namespace={{NAMESPACE}}' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testTitleClausesAreMappedToDplParameters(): void {
		$this->assertParsedResult( 'title = "Project Plan"', 'titlematch=Project Plan' );
		$this->assertParsedResult( 'title != "Project Plan"', 'nottitlematch=Project Plan' );
		$this->assertParsedResult( 'title ~ "Project"', 'titlematch=%Project%' );
		$this->assertParsedResult( 'title !~ "Project"', 'nottitlematch=%Project%' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testDateFieldsAreRejectedForDplParser(): void {
		$this->assertParsedResult( 'created >= "2024-01-01"', '' );
		$this->assertParsedResult( 'lastModified <= "2024-01-01"', '' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testAndClausesAreRenderedAsSeparateCriteria(): void {
		$this->assertParsedResult(
			'label = "dev" and space = "DEV"',
			"category=Dev\nnamespace=DEV"
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testOrClausesAreSupportedForSamePositiveFieldOnly(): void {
		$this->assertParsedResult(
			'label = "dev" or label = "test"',
			'category=Dev{{!}}Test'
		);
		$this->assertParsedResult(
			'space = "DEV" or space = "Test"',
			'namespace=DEV{{!}}Test'
		);

		$this->assertParsedResult( 'label = "dev" or space = "DEV"', '' );
		$this->assertParsedResult( 'label != "dev" or label != "test"', '' );
	}

	/**
	 * @param string $input
	 * @param string $expected
	 */
	private function assertParsedResult( string $input, string $expected ): void {
		$cqlParser = new DplCQLParser();
		$actual = $cqlParser->parse( $input );

		$this->assertEquals( $expected, $actual );
	}
}
