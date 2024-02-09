<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ConvertInfoMacro;
use PHPUnit\Framework\TestCase;

class ConvertInfoMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ConvertInfoMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$preprocessor = new ConvertInfoMacro();
		$preprocessor->process( $dom );

		$actualOutput = $dom->saveXML();

		$expectedOutput = $this->getExpectedOutput();

		$this->assertXmlStringEqualsXmlString(
			$expectedOutput,
			$actualOutput
		);
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/convertinfomacrotest-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/convertinfomacrotest-output.xml' );
	}
}
