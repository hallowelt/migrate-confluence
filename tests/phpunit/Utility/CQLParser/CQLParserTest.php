<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\CQLParser;

use HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser;
use HalloWelt\MigrateConfluence\Utility\CQLParser\SemanticMediaWikiCQLParser;
use PHPUnit\Framework\TestCase;

class CQLParserTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\SemanticMediaWikiCQLParser::buildQueryFromClauses()
	 */
	public function testSemanticMediaWikiParserExtendsBaseParser(): void {
		$this->assertTrue( is_subclass_of( SemanticMediaWikiCQLParser::class, CQLParser::class ) );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testLabelAndSpaceClausesAreConverted(): void {
		// Basic AND/OR conversion for label and space fields.
		$this->assertParsedResult(
			'label = "label_1" and label = "label_2"',
			'[[Category:Label_1]][[Category:Label_2]]'
		);
		$this->assertParsedResult(
			'label = "label_1" or label = "label_2"',
			'[[Category:Label_1]]|[[Category:Label_2]]'
		);
		$this->assertParsedResult(
			'label = "o-z" and space = currentSpace()',
			'[[Category:O-z]][[{{NAMESPACE}}:+]]'
		);
		$this->assertParsedResult( 'space = currentSpace()', '[[{{NAMESPACE}}:+]]' );
		$this->assertParsedResult(
			'label = "label_1" and space = "DEVOPS"',
			'[[Category:Label_1]][[DEVOPS:+]]'
		);
		$this->assertParsedResult( 'space = DEV and label = test', '[[DEV:+]][[Category:Test]]' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testNegationInAndNotInOperators(): void {
		// Negation can come from operator or leading NOT.
		$this->assertParsedResult(
			'label != "deprecated" and space != DEV',
			'[[!Category:Deprecated]][[!DEV:+]]'
		);
		$this->assertParsedResult(
			'label in ("a", b, c)',
			'[[Category:A]]|[[Category:B]]|[[Category:C]]'
		);
		$this->assertParsedResult( 'space in (DEV, QA)', '[[DEV:+]]|[[QA:+]]' );
		$this->assertParsedResult(
			'label not in (draft, review)',
			'[[!Category:Draft]]|[[!Category:Review]]'
		);
		$this->assertParsedResult( 'label = "cql" and not space = dev', '[[Category:Cql]][[!dev:+]]' );
		$this->assertParsedResult( 'label = "cql" and NOT space = dev', '[[Category:Cql]][[!dev:+]]' );
		$this->assertParsedResult( 'label = "cql" and   not    space = dev', '[[Category:Cql]][[!dev:+]]' );
		$this->assertParsedResult( 'label = "cql" and not space != dev', '[[Category:Cql]][[dev:+]]' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testOrderByIsIgnored(): void {
		// Sorting must not leak into generated conditions.
		$this->assertParsedResult( 'space = DEV order by created desc', '[[DEV:+]]' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testTypeControlsBlogNamespaceRewrite(): void {
		$this->assertParsedResult(
			'type = page and label = "o-z" and space = currentSpace()',
			'[[Category:O-z]][[{{NAMESPACE}}:+]]'
		);
		$this->assertParsedResult(
			'type = blog and label = "o-z" and space = currentSpace()',
			'[[Category:O-z]][[Blog:{{NAMESPACE}}:+]]'
		);
		$this->assertParsedResult( 'space = currentSpace() and type = blogpost', '[[Blog:{{NAMESPACE}}:+]]' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testKnownNonMappedFieldsAreValidatedAndIgnoredInOutput(): void {
		// Documented fields are accepted even when they do not produce SMW clauses yet.
		$this->assertParsedResult( 'label = "docs" and ancestor = "Home"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'label = "docs" and content = "api"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'created >= "2024-01-01" and label = "docs"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'creator = "jdoe" and space = DEV', '[[DEV:+]]' );
		$this->assertParsedResult( 'lastModified < "2024-12-31" and label = "docs"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'parent in ("Area", "Area/Sub") and label = "docs"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'pageStatus != archived and label = "docs"', '[[Category:Docs]]' );
		$this->assertParsedResult( 'title = "Roadmap" and space = QA', '[[QA:+]]' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser\CQLParser::parse()
	 */
	public function testUnsupportedFunctionUsageFailsClosed(): void {
		// `creator` does not support function calls, so parsing must fail.
		$this->assertParsedResult( 'creator = "99:abc"', '' );
	}

	/**
	 * @param string $input
	 * @param string $expected
	 */
	private function assertParsedResult( string $input, string $expected ): void {
		$cqlParser = new SemanticMediaWikiCQLParser();
		$actual = $cqlParser->parse( $input );

		$this->assertEquals( $expected, $actual );
	}
}
