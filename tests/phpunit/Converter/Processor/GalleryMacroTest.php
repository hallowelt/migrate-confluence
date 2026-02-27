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
	private function makeDataLookup( array $fileMap = [], array $spaceIdPrefixMap = [] ): ConversionDataLookup {
		return new ConversionDataLookup(
			$spaceIdPrefixMap,
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
			'1---MyPage---dashboard.png' => 'dashboard.png',
			'1---MyPage---photo.jpg' => 'photo.jpg',
			'1---MyPage---loading.gif' => 'loading.gif',
			'1---MyPage---illustration.webp' => 'illustration.webp',
			'1---MyPage---raw-scan.bmp' => 'raw-scan.bmp',
			'1---MyPage---blueprint.tiff' => 'blueprint.tiff',
			'1---MyPage---icon.svg' => 'icon.svg',
			'1---Brand_Assets---logo.png' => 'logo.png',
			'2---Team_Photos---team.jpg' => 'team.jpg',
			'1---MyPage---document.pdf' => 'document.pdf',
		];
		$spaceIdPrefixMap = [ 2 => 'MKT' ];
		$dataLookup = $this->makeDataLookup( $fileMap, $spaceIdPrefixMap );
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
