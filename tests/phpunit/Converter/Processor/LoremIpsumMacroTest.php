<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\LoremIpsumMacro;
use PHPUnit\Framework\TestCase;

class LoremIpsumMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\LoremIpsumMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = $this->getInput();

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$preprocessor = new LoremIpsumMacro();
		$preprocessor->process( $dom );

		$actualOutput = $dom->saveXML();

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/loremipsum-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/loremipsum-macro-output.xml' );
	}
}
