<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro;
use HalloWelt\MigrateConfluence\Utility\ConversionDataLookup;
use PHPUnit\Framework\TestCase;

class GalleryMacroTest extends TestCase {

	/**
	 * @return ConversionDataLookup
	 */
	private function makeDataLookup( array $fileMap = [] ): ConversionDataLookup {
		return new ConversionDataLookup(
			[],
			[],
			$fileMap,
			[],
			[],
			[]
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';

		$fileMap = [
			'1---MyPage---image1.png' => 'image1.png',
			'1---MyPage---image2.jpg' => 'image2.jpg',
			'1---MyPage---document.pdf' => 'document.pdf',
		];
		$dataLookup = $this->makeDataLookup( $fileMap );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage' );

		$dom = new DOMDocument();
		$dom->loadXML( file_get_contents( "$dir/gallery-macro-input.xml" ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/gallery-macro-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro::process
	 * @return void
	 */
	public function testProcessBrokenMacro() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';

		$dataLookup = $this->makeDataLookup();
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage' );

		$dom = new DOMDocument();
		$dom->loadXML( file_get_contents( "$dir/gallery-macro-broken-input.xml" ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/gallery-macro-broken-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
