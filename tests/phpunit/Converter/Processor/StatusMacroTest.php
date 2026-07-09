<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro;

class StatusMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\StatusMacro::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$processor = new StatusMacro();
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );
		$this->assertStringNotContainsString( 'ac:structured-macro', $output );
		$this->assertStringContainsString( '{{Status|title = Good Status|colour = Red}}', $output );
	}

	private function getInput(): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:ac="http://atlassian.com/content">
	<ac:structured-macro ac:name="status">
		<ac:parameter ac:name="title">Good Status</ac:parameter>
		<ac:parameter ac:name="colour">Red</ac:parameter>
	</ac:structured-macro>
</root>
XML;
	}
}
