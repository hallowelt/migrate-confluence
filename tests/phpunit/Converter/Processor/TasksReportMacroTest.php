<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataReader\ConverterDirectDataReader;
use HalloWelt\MigrateConfluence\Converter\Processor\TasksReportMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;

class TasksReportMacroTest extends ProcessorTestCase {
	/**
	 * @var mixed
	 */
	private $dataReader;

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TasksReportMacro::preprocess
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$this->dataReader = new ConverterDirectDataReader(
			( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat()
		);

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TasksReportMacro( $this->dataReader );
		$processor->process( $dom );

		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/task-report-macro-preserve-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/task-report-macro-preserve-output.xml' );
	}
}
