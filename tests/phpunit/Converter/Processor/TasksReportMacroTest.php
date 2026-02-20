<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TasksReportMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class TasksReportMacroTest extends TestCase {

	/**
	 * @var ConversionDataLookup
	 */
	private $dataLookup;

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TasksReportMacro::preprocess
	 * @return void
	 */
	public function testPreprocess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$this->dataLookup = new ConversionDataLookup(
			[
				42 => 'MT',
				23 => 'AB'
			],
			[
				'42---SomeLinkedPage' => 'ABC:SomeLinkedPage',
			],
			[
				'0---SomePage---SomeImage2.png' => 'SomePage_SomeImage2.png',
				'23---SomePage---SomeImage2.png' => 'DEVOPS_SomePage_SomeImage2.png'
			],
			[],
			[],
			[
				'123456' => 'TheFirstUser',
				'789456' => 'TheSecondUser',
			]
		);

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new TasksReportMacro( $this->dataLookup );
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
