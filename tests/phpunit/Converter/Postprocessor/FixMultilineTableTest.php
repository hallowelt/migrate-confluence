<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Preprocessor;

use HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable;
use PHPUnit\Framework\TestCase;

class FixMultilineTableTest extends TestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Postprocessor\FixMultilineTable::postprocess
	 * @return void
	 */
	public function testPostprocess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data';

		$input = $this->getInput();

		$preprocessor = new FixMultilineTable();
		$actualOutput = $preprocessor->postprocess( $input );

		$expectedOutput = $this->getExpectedOutput();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	protected function getInput(): string {
		return file_get_contents( $this->dir . '/fix-multiline-table-input.wikitext' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( $this->dir . '/fix-multiline-table-output.wikitext' );
	}
}
