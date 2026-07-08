<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\WarningMacro;

class WarningMacroTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\WarningMacro::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$processor = new WarningMacro();
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );
		$this->assertStringNotContainsString( 'ac:structured-macro', $output );
		$this->assertStringContainsString( '{{Important', $output );
		$this->assertStringContainsString( '|title = Warning title', $output );
		$this->assertStringContainsString( '|body = ', $output );
	}

	private function getInput(): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root xmlns:ac="http://atlassian.com/content">
	<ac:structured-macro ac:name="warning">
		<ac:parameter ac:name="title">Warning title</ac:parameter>
		<ac:rich-text-body>
			<p>This is a warning body.</p>
		</ac:rich-text-body>
	</ac:structured-macro>
</root>
XML;
	}
}
