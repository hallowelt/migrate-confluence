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
		$this->assertTrue(
			is_subclass_of( DplCQLParser::class, CQLParser::class ),
			'DplCQLParser must extend CQLParser to reuse base CQL validation and splitting logic.'
		);
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testLabelClausesAreMappedToDplParameters(): void {
		$this->assertParsedResult( 'label = "dev"', 'category=Dev' );
		$this->assertParsedResult( 'label != "dev"', 'notcategory=Dev' );
		$this->assertParsedResult( 'label in ("dev", "test")', 'category=Dev{{!}}Test' );
		$this->assertParsedResult( 'label not in ("dev", "test")', 'notcategory=Dev,notcategory=Test' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testSpaceClausesAreMappedToDplParameters(): void {
		$this->assertParsedResult( 'space = "DEV"', 'namespace=DEV' );
		$this->assertParsedResult( 'space != "DEV"', 'notnamespace=DEV' );
		$this->assertParsedResult( 'space in ("DEV", "Test")', 'namespace=DEV{{!}}Test' );
		$this->assertParsedResult( 'space not in ("DEV", "Test")', 'notnamespace=DEV,notnamespace=Test' );
		$this->assertParsedResult( 'space = currentSpace()', 'namespace={{NAMESPACE}}' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testTitleClausesAreRejectedForDplParser(): void {
		$this->assertParsedResult( 'title = "Project Plan"', '' );
		$this->assertParsedResult( 'title != "Project Plan"', '' );
		$this->assertParsedResult( 'title ~ "Project"', '' );
		$this->assertParsedResult( 'title !~ "Project"', '' );
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
			'category=Dev,namespace=DEV'
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
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testLabelOrThenAndClausesStayConvertible(): void {
		$this->assertParsedResult(
			'label = "label_1" or label = "label_2" and label = "label_3"',
			'category=Label_1{{!}}Label_2,category=Label_3'
		);
	}

	/**
	 * @param string $input
	 * @param string $expected
	 */
	private function assertParsedResult( string $input, string $expected ): void {
		$cqlParser = new DplCQLParser();
		$actual = $cqlParser->parse( $input );

		$this->assertSame(
			$expected,
			$actual,
			"Unexpected DPL mapping for CQL: '$input'. Expected: '$expected', got: '$actual'."
		);
	}
}
