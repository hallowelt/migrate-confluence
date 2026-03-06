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
	private function makeDataLookup(
		array $fileMap = [], array $spaceIdToKeyMap = [], array $labelsMap = []
	): ConversionDataLookup {
		return new ConversionDataLookup(
			[],
			[],
			$fileMap,
			[],
			[],
			[],
			$spaceIdToKeyMap,
			$labelsMap
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
		$spaceIdToKeyMap = [ 2 => 'MKT' ];
		$dataLookup = $this->makeDataLookup( $fileMap, $spaceIdToKeyMap );
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
	public function testProcessWithLabelFilter() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';

		// dashboard.png:  no labels              → excluded (missing both required labels)
		// photo.jpg:      [featured]              → excluded (AND: missing 'approved')
		// loading.gif:    [featured, draft]       → excluded (has 'draft')
		// approved.png:   [approved]              → excluded (AND: missing 'featured')
		// hero.jpg:       [featured, approved]    → included ✓
		// rejected.png:   [featured, approved, draft] → excluded (has 'draft')
		$fileMap = [
			'1---MyPage---dashboard.png' => 'dashboard.png',
			'1---MyPage---photo.jpg' => 'photo.jpg',
			'1---MyPage---loading.gif' => 'loading.gif',
			'1---MyPage---approved.png' => 'approved.png',
			'1---MyPage---hero.jpg' => 'hero.jpg',
			'1---MyPage---rejected.png' => 'rejected.png',
		];
		$labelsMap = [
			'1---MyPage---photo.jpg' => [ 'featured' ],
			'1---MyPage---loading.gif' => [ 'featured', 'draft' ],
			'1---MyPage---approved.png' => [ 'approved' ],
			'1---MyPage---hero.jpg' => [ 'featured', 'approved' ],
			'1---MyPage---rejected.png' => [ 'featured', 'approved', 'draft' ],
		];
		$dataLookup = $this->makeDataLookup( $fileMap, [], $labelsMap );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage' );

		$dom = new DOMDocument();
		$dom->loadXML( file_get_contents( "$dir/gallery-macro-label-input.xml" ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/gallery-macro-label-output.xml" ) );
		$expectedOutput = $expectedDom->saveXML( $expectedDom->documentElement );

		$this->assertEquals( $expectedOutput, $actualOutput );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro::process
	 * @return void
	 */
	public function testProcessPageParam() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';

		$fileMap = [
			'1---OtherPage---report.pdf' => 'report.pdf',
			'1---OtherPage---chart.png' => 'chart.png',
			'2---Marketing_Assets---logo.png' => 'logo.png',
		];
		$spaceIdToKeyMap = [ 2 => 'MKT' ];
		$dataLookup = $this->makeDataLookup( $fileMap, $spaceIdToKeyMap );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage' );

		$dom = new DOMDocument();
		$dom->loadXML( file_get_contents( "$dir/gallery-macro-page-input.xml" ) );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML( $dom->documentElement );

		$expectedDom = new DOMDocument();
		$expectedDom->loadXML( file_get_contents( "$dir/gallery-macro-page-output.xml" ) );
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
