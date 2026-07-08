<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro;

class DetailsMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname(  __DIR__, 2 ) . '/data';

		$input = file_get_contents( "$this->dir/details-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DetailsMacro();
		$processor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( "$this->dir/details-macro-output.xml" );
		$this->assertDomXmlEquals( $expectedDom, $dom );
	}

}
