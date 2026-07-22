<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class DrawioMacroTest extends ProcessorTestCase {
	/** @var DBConversionDataLookup */
	private $dataLookup;

	/** @var ConversionDataWriter */
	private $conversionDataWriter;

	/** @var string */
	private string $tempDir = '';

	protected function tearDown(): void {
		if ( $this->tempDir !== '' && is_dir( $this->tempDir ) ) {
			// Remove files inside the directory tree, then the directories
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
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro::process
	 * @return void
	 */
	public function testProcess() {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-drawio-test-' . uniqid();
		$this->conversionDataWriter = new ConversionDataWriter( $this->tempDir );
		$this->dataLookup = new DBConversionDataLookup( ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat() );

		$this->doTest( 0, 'drawio-macro-input.xml', 'drawio-macro-output-1.xml' );
		$this->doTest( 23, 'drawio-macro-input.xml', 'drawio-macro-output-2.xml' );
	}

	/**
	 * Regression test: when the macro's diagramName refers to a .drawio data file (not .png),
	 * the converter must look up the corresponding .png by appending ".png" and bake the
	 * diagram XML into that image's tEXt chunk.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\DrawioMacro::process
	 * @return void
	 */
	public function testBakesDiagramDataIntoImageWhenDiagramNameIsDataFile(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-drawio-bake-test-' . uniqid();
		mkdir( $this->tempDir, 0755, true );

		$dataDir = dirname( __DIR__, 2 ) . '/Utility/DrawIOFileHandler/data';
		$drawioDataFile = $dataDir . '/diagram.drawio';
		$drawioPngFile  = $dataDir . '/diagram.drawio.png';

		// Build a minimal workspace DB with one page containing both drawio files
		$db = ( new WorkspaceDbMock() )->createWithDrawioAttachments(
			1,
			'DiagramPage',
			'diagram.drawio',
			$drawioDataFile,
			'diagram.drawio.png',
			$drawioPngFile
		);

		$writerDir = $this->tempDir . '/writer';
		mkdir( $writerDir, 0755, true );
		$conversionDataWriter = new ConversionDataWriter( $writerDir );
		$dataLookup = new DBConversionDataLookup( $db );

		$macroXml = <<<'XML'
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="drawio" ac:schema-version="1" ac:macro-id="test">
		<ac:parameter ac:name="diagramName">diagram.drawio</ac:parameter>
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$processor = new DrawioMacro( $dataLookup, $conversionDataWriter, 1, 'DiagramPage' );
		$processor->process( $dom );

		// The PNG file must have been written with a tEXt chunk containing the diagram XML
		// ConversionDataWriter::replaceConfluenceFileContent saves to $writerDir/images/$targetFilename
		$writtenPng = $writerDir . '/images/DiagramPage-diagram.drawio.png';
		$this->assertFileExists( $writtenPng, 'Baked PNG was not written' );

		$pngContent = file_get_contents( $writtenPng );
		$this->assertNotFalse( $pngContent );

		// Count mxfile tEXt chunks
		$chunks = 0;
		// skip PNG signature
		$i = 8;
		while ( $i < strlen( $pngContent ) ) {
			$length = unpack( 'N', substr( $pngContent, $i, 4 ) )[1];
			$type = substr( $pngContent, $i + 4, 4 );
			if ( $type === 'tEXt' ) {
				$chunks++;
			}
			$i += 12 + $length;
		}
		$this->assertGreaterThanOrEqual( 1, $chunks, 'PNG must contain at least one mxfile tEXt chunk' );
	}

	/**
	 * @param int $spaceId
	 * @param string $input
	 * @param string $output
	 * @return void
	 */
	private function doTest( int $spaceId, string $input, string $output ) {
		$input = file_get_contents( dirname( __DIR__, 2 ) . "/data/$input" );
		$expectedOutput = file_get_contents( dirname( __DIR__, 2 ) . "/data/$output" );

		$dom = new DOMDocument();
		$dom->loadXML( $input );

		$processor = new DrawioMacro( $this->dataLookup, $this->conversionDataWriter, $spaceId, 'SomePage' );
		$processor->process( $dom );
		$actualOutput = $dom->saveXML();

		$this->assertEquals( $expectedOutput, $actualOutput );
	}
}
