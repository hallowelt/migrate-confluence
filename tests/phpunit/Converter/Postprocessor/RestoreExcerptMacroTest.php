<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Postprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreExcerptMacro;
use PHPUnit\Framework\TestCase;

class RestoreExcerptMacroTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\RestoreExcerptMacro::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = $this->getInput();

		$preprocessor = new RestoreExcerptMacro();
		$actualOutput = $preprocessor->postprocess( $input );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/restore-excerpt-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/restore-excerpt-macro-output.xml' );
	}
}
