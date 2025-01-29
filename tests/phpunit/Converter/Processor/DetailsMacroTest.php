<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro;
use PHPUnit\Framework\TestCase;

class DetailsMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DetailsMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/details-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DetailsMacro();
		$processor->process( $dom );

		$expectedDOM = new DOMDocument();
		$expectedDOM->load( "$this->dir/details-macro-output.xml" );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
	}

}
