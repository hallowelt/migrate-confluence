<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro;

class JiraMacroTest extends ProcessorTestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcess() {
		$jiraMacroProcessor = new JiraMacro();
		$dom = new \DOMDocument();
		$dom->load(
			dirname( __DIR__, 2 ) . '/data/jira-macro-input.xml'
		);
		$expectedDOM = new \DOMDocument();
		$expectedDOM->load(
			dirname( __DIR__, 2 ) . '/data/jira-macro-output.xml'
		);

		$jiraMacroProcessor->process( $dom );

		$this->assertDomXmlEquals( $expectedDOM, $dom );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessJql() {
		$this->doTest( 'jira-macro-jql-input.xml', 'jira-macro-jql-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessBrokenMacro() {
		$this->doTest( 'jira-macro-broken-input.xml', 'jira-macro-broken-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessKeyWinsOverJql() {
		$this->doTest( 'jira-macro-key-wins-input.xml', 'jira-macro-key-wins-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessJqlBoard() {
		$this->doTest( 'jira-macro-jql-board-input.xml', 'jira-macro-jql-board-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessJqlTimeline() {
		$this->doTest( 'jira-macro-jql-timeline-input.xml', 'jira-macro-jql-timeline-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessJqlCalendar() {
		$this->doTest( 'jira-macro-jql-calendar-input.xml', 'jira-macro-jql-calendar-output.xml' );
	}

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcessJqlSummary() {
		$this->doTest( 'jira-macro-jql-summary-input.xml', 'jira-macro-jql-summary-output.xml' );
	}

	/**
	 * @param string $inputFile
	 * @param string $outputFile
	 */
	private function doTest( string $inputFile, string $outputFile ): void {
		$processor = new JiraMacro();
		$dom = new \DOMDocument();
		$dom->load( dirname( __DIR__, 2 ) . '/data/' . $inputFile );
		$expectedDom = new \DOMDocument();
		$expectedDom->load( dirname( __DIR__, 2 ) . '/data/' . $outputFile );
		$processor->process( $dom );
		$this->assertDomXmlEquals( $expectedDom, $dom );
	}
}
