<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\TasksReportMacro;
use PHPUnit\Framework\TestCase;

class TasksReportMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\TasksReportMacro::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = $this->getInput();

		$preprocessor = new TasksReportMacro();
		$actualOutput = $preprocessor->postprocess( $input );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/task-report-macro-restore-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/task-report-macro-restore-output.xml' );
	}
}
