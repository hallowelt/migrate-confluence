<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroContenByLabel;

class StructuredMacroContenByLabelTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/structuredmacro-contentbylabel-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/structuredmacro-contentbylabel-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new StructuredMacroContenByLabel( 'SomePage' );
	}
}
