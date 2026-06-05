<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\IProcessor;
use HalloWelt\MigrateConfluence\Converter\Processor\ChildrenMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class ChildrenMacroTest extends StructuredMacroProcessorTestBase {
	protected function getInput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-input.xml' );
	}

	protected function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__, 2 ) . '/data/children-macro-output.xml' );
	}

	protected function getProcessorToTest(): IProcessor {
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		return new ChildrenMacro( 42, 'ABC:Some_page', $dataLookup );
	}
}
