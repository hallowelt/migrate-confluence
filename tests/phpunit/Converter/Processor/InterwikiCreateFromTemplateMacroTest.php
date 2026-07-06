<?php

namespace HalloWelt\MigrateConfluence\Tests\Converter\Processor;

use DOMDocument;
use HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro;
use HalloWelt\MigrateConfluence\Tests\Database\InterwikiDbMock;
use HalloWelt\MigrateConfluence\Utility\DBConversionDataLookup;
use PHPUnit\Framework\TestCase;

class InterwikiCreateFromTemplateMacroTest extends TestCase {

	/**
	 * Template in the same space as the current page resolves to the local wiki_title.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testSameWikiTemplateResolvesToLocalTitle(): void {
		$xml = $this->buildXml( 2001, 'Create Local' );

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		// Current page is in SPC1 (spaceId=1); template 2001 is also in SPC1
		$processor = new CreateFromTemplateMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString(
			'preload = Template:SPC1/LocalTemplate',
			$output,
			'Template in the same wiki should resolve to its local wiki_title'
		);
		$this->assertStringNotContainsString( '[[Category:Broken_macro', $output );
	}

	/**
	 * Template in a different space (different wiki) resolves to an interwiki title.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testDifferentWikiTemplateResolvesToInterwikiTitle(): void {
		$xml = $this->buildXml( 2002, 'Create Remote' );

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		// Current page is in SPC1 (spaceId=1); template 2002 is in SPC2
		$processor = new CreateFromTemplateMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString(
			'preload = wiki-space_2:Template:SPC2/RemoteTemplate',
			$output,
			'Template in a different wiki should be prefixed with the interwiki prefix'
		);
		$this->assertStringNotContainsString( '[[Category:Broken_macro', $output );
	}

	/**
	 * Template in SPC3 (different wiki) also resolves correctly.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testAnotherDifferentWikiTemplateResolvesToInterwikiTitle(): void {
		$xml = $this->buildXml( 2003, 'Create Another' );

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		// Current page is in SPC1 (spaceId=1); template 2003 is in SPC3
		$processor = new CreateFromTemplateMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString(
			'preload = wiki-space_3:Template:SPC3/AnotherRemoteTemplate',
			$output,
			'Template in SPC3 should be prefixed with wiki-space_3'
		);
		$this->assertStringNotContainsString( '[[Category:Broken_macro', $output );
	}

	/**
	 * Template in SPC4, which shares wiki-space_4 with SPC5.
	 * A page in SPC1 linking to a template in SPC4 gets the interwiki prefix.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testSharedWikiTemplateResolvesToInterwikiTitle(): void {
		$xml = $this->buildXml( 2004, 'Create Shared' );

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		// Current page is in SPC1 (spaceId=1); template 2004 is in SPC4
		$processor = new CreateFromTemplateMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString(
			'preload = wiki-space_4:Template:SPC4/SharedWikiTemplate',
			$output,
			'Template in SPC4 should be prefixed with wiki-space_4'
		);
		$this->assertStringNotContainsString( '[[Category:Broken_macro', $output );
	}

	/**
	 * Multiple templates across different wikis all resolve in a single pass.
	 *
	 * @covers HalloWelt\MigrateConfluence\Converter\Processor\CreateFromTemplateMacro::process
	 * @return void
	 */
	public function testMultipleTemplatesAcrossWikis(): void {
		$xml = '<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. $this->buildMacroFragment( 2001, 'Local', 'macro-id-1' )
			. $this->buildMacroFragment( 2002, 'Remote SPC2', 'macro-id-2' )
			. $this->buildMacroFragment( 2004, 'Remote SPC4', 'macro-id-3' )
			. '</xml>';

		$dom = new DOMDocument();
		$dom->loadXML( $xml );

		$workspaceDB = ( new InterwikiDbMock() )->create();
		$dataLookup = new DBConversionDataLookup( $workspaceDB );

		$processor = new CreateFromTemplateMacro( $dataLookup, 1 );
		$processor->process( $dom );

		$output = $dom->saveXML( $dom->documentElement );

		$this->assertStringContainsString( 'preload = Template:SPC1/LocalTemplate', $output );
		$this->assertStringContainsString( 'preload = wiki-space_2:Template:SPC2/RemoteTemplate', $output );
		$this->assertStringContainsString( 'preload = wiki-space_4:Template:SPC4/SharedWikiTemplate', $output );
		$this->assertStringNotContainsString( '[[Category:Broken_macro', $output );
	}

	/**
	 * @param int $templateId
	 * @param string $buttonLabel
	 * @return string
	 */
	private function buildXml( int $templateId, string $buttonLabel ): string {
		return '<xml xmlns:ac="sample_namespace" xmlns:ri="sample_second_namespace">'
			. $this->buildMacroFragment( $templateId, $buttonLabel, 'test-macro-id' )
			. '</xml>';
	}

	/**
	 * @param int $templateId
	 * @param string $buttonLabel
	 * @param string $macroId
	 * @return string
	 */
	private function buildMacroFragment( int $templateId, string $buttonLabel, string $macroId ): string {
		return '<ac:structured-macro ac:name="create-from-template" ac:schema-version="1"'
			. ' ac:macro-id="' . htmlspecialchars( $macroId ) . '">'
			. '<ac:parameter ac:name="templateName">' . $templateId . '</ac:parameter>'
			. '<ac:parameter ac:name="templateId">' . $templateId . '</ac:parameter>'
			. '<ac:parameter ac:name="buttonLabel">' . htmlspecialchars( $buttonLabel ) . '</ac:parameter>'
			. '</ac:structured-macro>';
	}
}
