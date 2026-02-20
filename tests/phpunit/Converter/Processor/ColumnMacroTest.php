<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ColumnMacro;

class ColumnMacroTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/column-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/column-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new ColumnMacro();
	}
}
