<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\Table;
use PHPUnit\Framework\TestCase;

class TableTest extends TestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Preprocessor\dom\Table::preprocess
	 *
	 * @return void
	 */
	public function testPreprocess(): void {
		$path = dirname( __DIR__, 2 ) . '/data/';
		$input = file_get_contents( $path . 'table-input.xml' );
		$expectedXml = file_get_contents( $path . 'table-output.xml' );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$preprocessor = new Table();
		$preprocessor->preprocess( $dom );

		// Test the resulting XML
		$actualXml = $dom->saveXML();
		$this->assertEquals( $expectedXml, $actualXml );
	}

}
