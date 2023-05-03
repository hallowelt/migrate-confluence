<?php

namespace HalloWelt\MigrateConfluence\Tests\Utility\CQLParser;

use HalloWelt\MigrateConfluence\Utility\CQLParser;
use PHPUnit\Framework\TestCase;

class CQLParserTest extends TestCase {
	/**
	 * @covers \HalloWelt\MigrateConfluence\Utility\CQLParser::parse()
	 */
	public function testParse() {
		$cqlParser = new CQLParser();

		$input = 'label = "label_1" and label = "label_2"';
		$expected = '[[Category:Label_1]][[Category:Label_2]]';

		$actual = $cqlParser->parse( $input );

		$this->assertEquals( $expected, $actual );
	}

}
