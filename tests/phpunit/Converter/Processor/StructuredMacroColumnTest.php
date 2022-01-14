<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroColumn;

class StructuredMacroColumnTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrocolumntest-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/structuredmacrocolumntest-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor	{
		return new StructuredMacroColumn();
	}
}
