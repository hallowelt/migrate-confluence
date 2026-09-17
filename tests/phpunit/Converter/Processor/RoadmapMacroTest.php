<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\DataWriter\ConverterDirectDataWriter;
use HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class RoadmapMacroTest extends ProcessorTestCase {

	/** @var string */
	private string $tempDir = '';

	protected function tearDown(): void {
		if ( $this->tempDir !== '' && is_dir( $this->tempDir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $this->tempDir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $iterator as $entry ) {
				$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
			}
			rmdir( $this->tempDir );
			$this->tempDir = '';
		}
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro::process
	 * @return void
	 */
	public function testProcessRendersSvgAndEmitsTemplateCall(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-roadmap-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$source = [
			'title' => 'Roadmap Planner',
			'timeline' => [ 'startDate' => '2026-01-01 00:00:00', 'endDate' => '2026-02-01 00:00:00' ],
			'lanes' => [
				[
					'title' => 'Lane 1',
					'color' => [ 'lane' => '#f6c342', 'bar' => '#f6c342', 'text' => '#594300' ],
					'bars' => [
						[
							'title' => 'Bar 1',
							'startDate' => '2026-01-10 00:00:00',
							'duration' => 1,
							'rowIndex' => 0,
							// Resolves to 'ABC:SomePage' via WorkspaceDbMock's first seeded page mapping.
							'pageLink' => [ 'id' => '10000' ],
						],
					],
				],
			],
			'markers' => [],
		];
		$encodedSource = rawurlencode( json_encode( $source ) );

		$macroXml = <<<XML
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="roadmap" ac:schema-version="1" data-layout="default" ac:macro-id="test-macro-id">
		<ac:parameter ac:name="timeline">true</ac:parameter>
		<ac:parameter ac:name="source">$encodedSource</ac:parameter>
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$dataWriter = $this->createMock( ConverterDirectDataWriter::class );
		$dataWriter->expects( $this->once() )->method( 'addRoadmapSvg' )
			->with( 1, 'SomePage', 'Roadmap-test-macro-id.svg' );

		$processor = new RoadmapMacro( $dataLookup, $conversionDataWriter, $dataWriter, 1, 'SomePage' );
		$processor->process( $dom );

		$writtenSvg = $this->tempDir . '/images/Roadmap-test-macro-id.svg';
		$this->assertFileExists( $writtenSvg, 'SVG file was not written' );

		$svg = file_get_contents( $writtenSvg );
		$this->assertStringContainsString( '<svg', $svg );
		$this->assertStringContainsString( 'Lane 1', $svg );
		$this->assertStringContainsString( 'Bar 1', $svg );
		$this->assertStringContainsString( 'text-anchor="middle"', $svg );
		$this->assertStringContainsString( '<style>text{font-family:sans-serif}</style>', $svg );
		$this->assertStringContainsString( 'href="#internal#ABC:SomePage"', $svg );

		$output = $dom->saveXML();
		$this->assertStringContainsString( '{{Roadmap', $output );
		$this->assertStringContainsString( '|filename=Roadmap-test-macro-id.svg', $output );
		$this->assertStringContainsString( '|layout=default', $output );
		$this->assertStringContainsString( '|timeline=true', $output );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro::process
	 * @return void
	 */
	public function testProcessFallsBackToConfluenceUrlWhenPageIdIsNotMigrated(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-roadmap-unresolved-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$source = [
			'title' => 'Roadmap Planner',
			'timeline' => [ 'startDate' => '2026-01-01 00:00:00', 'endDate' => '2026-02-01 00:00:00' ],
			'lanes' => [
				[
					'title' => 'Lane 1',
					'color' => [ 'lane' => '#f6c342', 'bar' => '#f6c342', 'text' => '#594300' ],
					'bars' => [
						[
							'title' => 'Bar 1',
							'startDate' => '2026-01-10 00:00:00',
							'duration' => 1,
							'rowIndex' => 0,
							// Unknown page ID, not seeded in the mock DB.
							'pageLink' => [ 'id' => '999999999' ],
						],
					],
				],
			],
			'markers' => [],
		];
		$encodedSource = rawurlencode( json_encode( $source ) );

		$macroXml = <<<XML
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="roadmap" ac:schema-version="1" data-layout="default" ac:macro-id="test-macro-id">
		<ac:parameter ac:name="source">$encodedSource</ac:parameter>
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$dataWriter = $this->createMock( ConverterDirectDataWriter::class );

		$processor = new RoadmapMacro( $dataLookup, $conversionDataWriter, $dataWriter, 1, 'SomePage' );
		$processor->process( $dom );

		$svg = file_get_contents( $this->tempDir . '/images/Roadmap-test-macro-id.svg' );
		$this->assertStringContainsString(
			'href="#internal#/pages/viewpage.action?pageId=999999999"',
			$svg,
			'Unresolved page IDs should fall back to a Confluence URL, still #internal#-prefixed'
		);
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro::process
	 * @return void
	 */
	public function testProcessDoesNotPrefixAbsoluteUrls(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-roadmap-url-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$source = [
			'title' => 'Roadmap Planner',
			'timeline' => [ 'startDate' => '2026-01-01 00:00:00', 'endDate' => '2026-02-01 00:00:00' ],
			'lanes' => [
				[
					'title' => 'Lane 1',
					'color' => [ 'lane' => '#f6c342', 'bar' => '#f6c342', 'text' => '#594300' ],
					'bars' => [
						[
							'title' => 'Bar 1',
							'startDate' => '2026-01-10 00:00:00',
							'duration' => 1,
							'rowIndex' => 0,
							'pageLink' => [ 'url' => 'https://example.org/some-page' ],
						],
					],
				],
			],
			'markers' => [],
		];
		$encodedSource = rawurlencode( json_encode( $source ) );

		$macroXml = <<<XML
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="roadmap" ac:schema-version="1" data-layout="default" ac:macro-id="test-macro-id">
		<ac:parameter ac:name="source">$encodedSource</ac:parameter>
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$dataWriter = $this->createMock( ConverterDirectDataWriter::class );

		$processor = new RoadmapMacro( $dataLookup, $conversionDataWriter, $dataWriter, 1, 'SomePage' );
		$processor->process( $dom );

		$svg = file_get_contents( $this->tempDir . '/images/Roadmap-test-macro-id.svg' );
		$this->assertStringContainsString( 'href="https://example.org/some-page"', $svg );
		$this->assertStringNotContainsString( '#internal#', $svg );
	}

	/**
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\RoadmapMacro::process
	 * @return void
	 */
	public function testProcessAddsBrokenMacroCategoryWhenSourceIsMissing(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-roadmap-broken-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );
		$dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$macroXml = <<<'XML'
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="roadmap" ac:schema-version="1" data-layout="default" ac:macro-id="test-macro-id">
		<ac:parameter ac:name="timeline">true</ac:parameter>
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$dataWriter = $this->createMock( ConverterDirectDataWriter::class );
		$dataWriter->expects( $this->never() )->method( 'addRoadmapSvg' );

		$processor = new RoadmapMacro( $dataLookup, $conversionDataWriter, $dataWriter, 1, 'SomePage' );
		$processor->process( $dom );

		$output = $dom->saveXML();
		$this->assertStringContainsString( '[[Category:Broken_macro/roadmap]]', $output );
	}
}
