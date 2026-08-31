<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\IncDrawioMacro;
use HalloWelt\MigrateConfluence\Tests\Database\WorkspaceDbMock;
use HalloWelt\MigrateConfluence\Utility\ConversionDataWriter;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;

class IncDrawioMacroTest extends ProcessorTestCase {

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
	 * The "inc-drawio" macro embeds a diagram that is attached to a *different* page
	 * (referenced via "pageId"), not the page the macro itself is placed on.
	 * The diagram's attachment must therefore be looked up on the referenced page/space,
	 * and the "pageId"/"includedDiagram" parameters must not leak into the resulting template call.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncDrawioMacro::process
	 * @return void
	 */
	public function testProcessResolvesDiagramFromReferencedPage(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-inc-drawio-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );

		$workspaceDB = ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		// Find the page_id of space 23's "SomePage" (DEVOPS:SomePage), which owns the "drawio.png" attachment.
		$pageIdMap = $workspaceDB->getPageIdWikiPageTitleMap( 23 );
		$sourcePageId = array_search( 'DEVOPS:SomePage', $pageIdMap, true );
		$this->assertNotFalse( $sourcePageId, 'Could not find fixture page DEVOPS:SomePage' );

		$macroXml = <<<XML
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="inc-drawio" ac:schema-version="1" ac:macro-id="test">
		<ac:parameter ac:name="diagramName">drawio.png</ac:parameter>
		<ac:parameter ac:name="includedDiagram">1</ac:parameter>
		<ac:parameter ac:name="width">881</ac:parameter>
		<ac:parameter ac:name="pageId">$sourcePageId</ac:parameter>
		<ac:parameter ac:name="" />
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		// The macro is being processed as if it appeared on space 42's "SomePage", which has
		// no "drawio.png" attachment of its own - the diagram must come from space 23 instead.
		$processor = new IncDrawioMacro( $dataLookup, $conversionDataWriter, 42, 'SomePage' );
		$processor->process( $dom );

		$actualOutput = $dom->documentElement->textContent;

		$this->assertStringContainsString( '{{Drawio', $actualOutput );
		$this->assertStringContainsString( '|diagramName=DEVOPS:SomePage-drawio.png', $actualOutput );
		$this->assertStringContainsString( '|width=881', $actualOutput );
		$this->assertStringNotContainsString( 'pageId', $actualOutput );
		$this->assertStringNotContainsString( 'includedDiagram', $actualOutput );
	}

	/**
	 * If the "pageId" cannot be resolved, the macro should fall back to looking up the
	 * diagram on the current page/space instead of failing outright.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\IncDrawioMacro::process
	 * @return void
	 */
	public function testProcessFallsBackToCurrentPageWhenPageIdIsUnknown(): void {
		$this->tempDir = sys_get_temp_dir() . '/confluence-migration-inc-drawio-fallback-test-' . uniqid();
		$conversionDataWriter = new ConversionDataWriter( $this->tempDir );

		$workspaceDB = ( new WorkspaceDbMock() )->createWithExtNsFileRepoCompat();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$macroXml = <<<'XML'
<xml xmlns:ac="some" xmlns:ri="thing">
	<ac:structured-macro ac:name="inc-drawio" ac:schema-version="1" ac:macro-id="test">
		<ac:parameter ac:name="diagramName">drawio.png</ac:parameter>
		<ac:parameter ac:name="includedDiagram">1</ac:parameter>
		<ac:parameter ac:name="width">881</ac:parameter>
		<ac:parameter ac:name="pageId">999999999</ac:parameter>
		<ac:parameter ac:name="" />
	</ac:structured-macro>
</xml>
XML;

		$dom = new DOMDocument();
		$dom->loadXML( $macroXml );

		$processor = new IncDrawioMacro( $dataLookup, $conversionDataWriter, 23, 'SomePage' );
		$processor->process( $dom );

		$actualOutput = $dom->documentElement->textContent;

		$this->assertStringContainsString( '|diagramName=DEVOPS:SomePage-drawio.png', $actualOutput );
	}
}
