<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\TipMacro;

class TipMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\TipMacro::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$processor = new TipMacro();
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );
		$this->assertStringNotContainsString( 'ac:structured-macro', $output );
		$this->assertStringContainsString( '{{Success', $output );
		$this->assertStringContainsString( '|title = Tip title', $output );
		$this->assertStringContainsString( '|body = ', $output );
	}

	private function getInput(): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:ac="http://atlassian.com/content">
	<ac:structured-macro ac:name="tip">
		<ac:parameter ac:name="title">Tip title</ac:parameter>
		<ac:rich-text-body>
			<p>This is a tip body.</p>
		</ac:rich-text-body>
	</ac:structured-macro>
</root>
XML;
	}
}
