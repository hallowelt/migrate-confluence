<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro;

class ExcerptMacroTest extends ProcessorTestCase {

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\ExcerptMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->dir = dirname( __DIR__, 2 ) . '/data/PageExcerpt';

		$input = file_get_contents( "$this->dir/excerpt-macro-input.xml" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new ExcerptMacro( $this->createConverterDataWriter(), 1 );
		$processor->process( $dom );

		$expectedDom = new DOMDocument();
		$expectedDom->load( "$this->dir/MediaWiki/excerpt-macro-output.xml" );
		$this->assertDomXmlEquals( $expectedDom, $dom );
	}

}
