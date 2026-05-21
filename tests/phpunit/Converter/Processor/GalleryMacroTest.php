<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use HalloWelt\MigrateConfluence\Utility\MigrationConfig;
use PHPUnit\Framework\TestCase;

class GalleryMacroTest extends TestCase {
	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\GalleryMacro::process
	 * @return void
	 */
	public function testProcess() {
		$dir = dirname( dirname( __DIR__ ) ) . '/data';
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage', new MigrationConfig( [] ) );

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
		$dir = dirname( __DIR__, 2 ) . '/data';

		// dashboard.png:  no labels              → excluded (missing both required labels)
		// photo.jpg:      [featured]              → excluded (AND: missing 'approved')
		// loading.gif:    [featured, draft]       → excluded (has 'draft')
		// approved.png:   [approved]              → excluded (AND: missing 'featured')
		// hero.jpg:       [featured, approved]    → included ✓
		// rejected.png:   [featured, approved, draft] → excluded (has 'draft')
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage', new MigrationConfig( [] ) );

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
		$dir = dirname( __DIR__, 2 ) . '/data';

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage', new MigrationConfig( [] ) );

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
		$dir = dirname( __DIR__, 2 ) . '/data';

		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithoutExtNsFileRepoCompat() );
		$processor = new GalleryMacro( $dataLookup, 1, 'MyPage without attachments', new MigrationConfig( [] ) );

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
