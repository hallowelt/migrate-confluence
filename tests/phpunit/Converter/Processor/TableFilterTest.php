<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TableFilter;
use PHPUnit\Framework\TestCase;

class TableFilterTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TableFilter::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/table-filter-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TableFilter();
		$processor->process( $dom );

		$expectedDOM = new DOMDocument();
		$expectedDOM->load( "$this->dir/table-filter-macro-output.xml" );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
	}

}
