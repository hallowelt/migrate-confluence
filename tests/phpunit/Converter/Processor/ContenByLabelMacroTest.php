<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ContenByLabelMacro;

class ContenByLabelMacroTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/contentbylabel-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/contentbylabel-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new ContenByLabelMacro( 'SomePage' );
	}
}
