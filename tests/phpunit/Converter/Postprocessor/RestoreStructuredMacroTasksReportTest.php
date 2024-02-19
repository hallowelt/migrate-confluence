<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreStructuredMacroTasksReport;
use PHPUnit\Framework\TestCase;

class RestoreStructuredMacroTasksReportTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreStructuredMacroTasksReport::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$this->dir = dirname( dirname( __DIR__ ) ) . '/data';

		$input = $this->getInput();

		$preprocessor = new RestoreStructuredMacroTasksReport();
		$actualOutput = $preprocessor->postprocess( $input );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/restore.structured-macro-task-report-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/restore.structured-macro-task-report-output.xml' );
	}
}
