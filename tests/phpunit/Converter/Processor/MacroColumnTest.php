<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

class MacroColumnTest extends MacroPanelTest {

	public function getInput(): string {
		return file_get_contents( dirname( __DIR__ ) . '/../data/structuredmacrocolumntest-input.xml' );
	}

	public function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__ ) . '/../data/structuredmacrocolumntest-output.xml' );
	}
}
