<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;

class ChildrenMacroTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/children-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( dirname( __DIR__ ) ) . '/data/children-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		return new ChildrenMacro( 'SomePage' );
	}
}
