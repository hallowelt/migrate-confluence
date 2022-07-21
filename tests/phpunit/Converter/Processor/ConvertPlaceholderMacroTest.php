<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertPlaceholderMacro;
use PHPUnit\Framework\TestCase;

class ConvertPlaceholderMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertPlaceholderMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/placeholdermacro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ConvertPlaceholderMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/placeholdermacro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
