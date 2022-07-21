<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertTaskMacro;
use PHPUnit\Framework\TestCase;

class ConvertTaskMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertTaskMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = file_get_contents( "$this->dir/taskmacro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ConvertTaskMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );
		$expectedOutput = file_get_contents( "$this->dir/taskmacro-output.xml" );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

}
