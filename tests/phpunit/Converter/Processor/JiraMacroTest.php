<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro;
use PHPUnit\Framework\TestCase;

class JiraMacroTest extends TestCase {

	/**
	 * @covers \HalloWelt\MigrateConfluence\Converter\Processor\JiraMacro::process
	 * @return void
	 */
	public function testProcess() {
		$jiraMacroProcessor = new JiraMacro();
		$dom = new \DOMDocument();
		$dom->load(
			__DIR__ . '/../../data/jira-macro-input.xml'
		);
		$expectedDOM = new \DOMDocument();
		$expectedDOM->load(
			__DIR__ . '/../../data/jira-macro-output.xml'
		);

		$jiraMacroProcessor->process( $dom );

		$this->assertEqualXMLStructure(
			$expectedDOM->documentElement,
			$dom->documentElement
		);
	}
}
