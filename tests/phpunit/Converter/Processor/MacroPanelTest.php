<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\MacroColumn;
use PHPUnit\Framework\TestCase;

class MacroPanelTest extends TestCase {

	public function getInput(): string {
		return file_get_contents( dirname( __DIR__ ) . '/../data/structuredmacropaneltest-input.xml' );
	}

	public function getExpectedOutput(): string {
		return file_get_contents( dirname( __DIR__ ) . '/../data/structuredmacropaneltest-output.xml' );
	}
}