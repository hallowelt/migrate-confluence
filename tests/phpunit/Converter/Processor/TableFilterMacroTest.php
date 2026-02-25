<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro;
use PHPUnit\Framework\TestCase;

class TableFilterMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableFilterMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/table-filter-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TableFilterMacro();
		$processor->process( $dom );

		$expectedDOM = new DOMDocument();
		$expectedDOM->load( "$this->dir/table-filter-macro-output.xml" );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
	}

}
