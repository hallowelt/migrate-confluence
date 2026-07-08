<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\NoteMacro;

class NoteMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\NoteMacro::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$processor = new NoteMacro();
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );
		$this->assertStringNotContainsString( 'ac:structured-macro', $output );
		$this->assertStringContainsString( '{{Warning', $output );
		$this->assertStringContainsString( '|title = Heads up', $output );
		$this->assertStringContainsString( '|body = ', $output );
	}

	private function getInput(): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:ac="http://atlassian.com/content">
	<ac:structured-macro ac:name="note">
		<ac:parameter ac:name="title">Heads up</ac:parameter>
		<ac:rich-text-body>
			<p>This is a note body.</p>
		</ac:rich-text-body>
	</ac:structured-macro>
</root>
XML;
	}
}
