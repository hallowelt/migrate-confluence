<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroJira;
use PHPUnit\Framework\TestCase;

class StructuredMacroJiraTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\StructuredMacroJira::process
	 * @return void
	 */
	public function testProcess() {
		$jiraMacroProcessor = new StructuredMacroJira();
		$dom = new \DOMDocument();
		$dom->load(
			__DIR__ . '/../../data/jira-input.xml'
		);
		$expectedDOM = new \DOMDocument();
		$expectedDOM->load(
			__DIR__ . '/../../data/jira-output.xml'
		);

		$jiraMacroProcessor->process( $dom );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
	}
}
