<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;

class PreservePStyleTagTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$processor = new PreservePStyleTag();
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );
		$this->assertStringContainsString( '#####PRESERVEPSTYLEOPEN style="color:red;"#####', $output );
		$this->assertStringContainsString( '#####PRESERVEPSTYLECLOSE#####', $output );
		$this->assertStringNotContainsString( '#####PRESERVEPSTYLEOPEN class="x"#####', $output );
	}

	private function getInput(): string {
		return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
	<p style="color:red;">Styled paragraph</p>
	<p class="x" style="font-weight:bold;">Should not be wrapped</p>
	<p>Plain paragraph</p>
</root>
XML;
	}
}
