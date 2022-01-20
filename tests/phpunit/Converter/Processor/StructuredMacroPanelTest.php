<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroPanel;

class StructuredMacroPanelTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacropaneltest-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacropaneltest-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new StructuredMacroPanel();
	}
}
