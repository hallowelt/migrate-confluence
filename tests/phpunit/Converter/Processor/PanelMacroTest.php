<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\PanelMacro;

class PanelMacroTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname(  __DIR__, 2 ) . '/data/panel-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname(  __DIR__, 2 ) . '/data/panel-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new PanelMacro();
	}
}
