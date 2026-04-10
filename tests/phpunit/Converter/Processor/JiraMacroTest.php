<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro;
use PHPUnit\Framework\TestCase;

class JiraMacroTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcess() {
		$jiraMacroProcessor = new JiraMacro();
		$dom = new \DOMDocument();
		$dom->load(
			__DIR__ . '/../../data/jira-macro-input.xml'
		);
		$expectedDOM = new \DOMDocument();
		$expectedDOM->load(
			__DIR__ . '/../../data/jira-macro-output.xml'
		);

		$jiraMacroProcessor->process( $dom );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
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
	 * @param string $inputFile
	 * @param string $outputFile
	 */
	private function doTest( string $inputFile, string $outputFile ): void {
		$processor = new JiraMacro();
		$dom = new \DOMDocument();
		$dom->load( __DIR__ . '/../../data/' . $inputFile );
		$expected = file_get_contents( __DIR__ . '/../../data/' . $outputFile );
		$processor->process( $dom );
		$this->assertEquals( $expected, $dom->saveXML() );
	}
}
