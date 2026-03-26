<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;

class ChildrenMacroTest extends StructuredMacroProcessorTestBase {

	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		$dataLookup = new ConversionDataLookup(
			[
				42 => 'ABC:',
				23 => 'DEVOPS:'
			],
			[
				'42---Some_page' => 'ABC:Some_page',
				'23---Some_other_page' => 'DEVOPS:Some_other_page',
			],
			[],
			[],
			[],
			[],
			[
				42 => 'ABC',
				23 => 'DEVOPS'
			],
			[],
			[]
		);
		return new ChildrenMacro( 42, 'ABC:Some_page', $dataLookup );
	}
}
