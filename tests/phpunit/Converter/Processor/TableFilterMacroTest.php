<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro;

class TableFilterMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/table-filter-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TableFilterMacro();
		$processor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( "$this->dir/table-filter-macro-output.xml" );
		$this->assertDomXmlEquals( $expectedDom, $dom );
	}

}
