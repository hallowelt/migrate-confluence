<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag;
use HalloWelt\MigrateConfluence\Utility\PlaceholderManager;

class PreservePStyleTagTest extends ProcessorTestCase {

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\PreservePStyleTag::process
	 * @return void
	 */
	public function testProcess(): void {
		$dom = new DOMDocument();
		$dom->loadXML( $this->getInput() );

		$placeholderManager = new PlaceholderManager();
		$processor = new PreservePStyleTag( $placeholderManager );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		// the processor registers the open/close placeholders in this order for the
		// first (and only) eligible paragraph
		$this->assertStringContainsString( 'convert:placeholder=0Styled paragraphconvert:placeholder=1', $output );
		$resolvedOpen = $placeholderManager->replacePlaceholders( 'convert:placeholder=0' );
		$this->assertSame( '<p style="color:red;">', $resolvedOpen );
		$resolvedClose = $placeholderManager->replacePlaceholders( 'convert:placeholder=1' );
		$this->assertSame( '</p>', $resolvedClose );

		// the mixed-attribute and plain paragraphs must stay untouched
		$paragraphs = $dom->getElementsByTagName( 'p' );
		$this->assertSame( 'Should not be wrapped', $paragraphs->item( 1 )->textContent );
		$this->assertSame( 'Plain paragraph', $paragraphs->item( 2 )->textContent );
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
