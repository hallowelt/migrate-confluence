<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TaskListMacro;
use PHPUnit\Framework\TestCase;

class TaskListMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TaskListMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TaskListMacro();
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/task-list-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/task-list-macro-output.xml' );
	}

}
