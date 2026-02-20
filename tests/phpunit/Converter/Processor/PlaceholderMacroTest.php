<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PlaceholderMacro;
use PHPUnit\Framework\TestCase;

class PlaceholderMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PlaceholderMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/placeholder-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new PlaceholderMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/placeholder-macro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
