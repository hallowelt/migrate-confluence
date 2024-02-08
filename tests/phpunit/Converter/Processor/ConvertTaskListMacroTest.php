<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertTaskListMacro;
use PHPUnit\Framework\TestCase;

class ConvertTaskListMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertTaskListMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ConvertTaskListMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/task-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/task-macro-output.xml' );
	}

}
