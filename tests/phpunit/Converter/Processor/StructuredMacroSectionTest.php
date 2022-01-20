<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroSection;

class StructuredMacroSectionTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrosectiontest-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrosectiontest-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new StructuredMacroSection();
	}
}
